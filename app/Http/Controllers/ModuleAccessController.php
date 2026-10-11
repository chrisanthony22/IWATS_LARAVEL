<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\DataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModuleAccessController extends Controller
{
    private const ACTIONS = ['view', 'create', 'edit', 'delete'];

    public function __construct(private DataService $data) {}

    public function index(Request $request): View
    {
        $types  = $this->data->getUserTypes();
        $typeId = (int) $request->query('type', 0);

        if (! $types->contains('ID', $typeId)) {
            $typeId = (int) ($types->first()->ID ?? 0);      // default to the first user type
        }

        return view('module-access.index', [
            'userTypes' => $types,
            'typeId'    => $typeId,
            'modules'   => $this->data->getModules(),
            'access'    => $this->data->getAccessByModuleId($typeId),
            'locked'    => $this->isAdminType($typeId),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'usertype_id' => ['required', 'integer', Rule::exists('IWMS_UserTypes', 'ID')],
        ]);

        $typeId   = $request->integer('usertype_id');
        $typeName = $this->data->getUserTypes()->firstWhere('ID', $typeId)?->UserType ?? "#{$typeId}";

        if ($this->isAdminType($typeId)) {
            return back()->with('error', "{$typeName} is an administrator type. It always has full access and cannot be changed here.");
        }

        $modules = $this->data->getModules();
        $oldRows = $this->data->getAccessByModuleId($typeId);
        $newRows = [];

        foreach ($modules as $m) {
            $flags = [];
            foreach (self::ACTIONS as $a) {
                $flags[$a] = $request->boolean("access.{$m->ID}.{$a}");
            }
            if ($flags['create'] || $flags['edit'] || $flags['delete']) {
                $flags['view'] = true;                        // you cannot create/edit/delete what you cannot open
            }
            $newRows[(int) $m->ID] = $flags;
        }

        // what changed, in words, for the audit trail
        $label = fn (?array $f) => $f ? (implode(', ', array_keys(array_filter($f))) ?: 'none') : 'none';
        $old = $new = [];
        foreach ($modules as $m) {
            $before = $label($oldRows[(int) $m->ID] ?? null);
            $after  = $label($newRows[(int) $m->ID]);
            if ($before !== $after) {
                $old[$m->ModuleName] = $before;
                $new[$m->ModuleName] = $after;
            }
        }

        if (! $new) {
            return back()->with('success', 'No changes to save.');
        }

        $this->data->saveAccess($typeId, $newRows);

        Audit::log('updated',
            "Changed module access of user type {$typeName} (" . count($new) . ' module(s)).',
            ['IWMS_UserTypeAccess', $typeId], $old, $new);

        return redirect()->route('module-access.index', ['type' => $typeId])
            ->with('success', "Access for {$typeName} was saved. It applies right away.");
    }

    private function isAdminType(int $typeId): bool
    {
        return in_array($typeId, config('iwats.admin_type_ids', []), true);
    }
}