<?php

return [
    // User type IDs that always have full access (comma-separated in .env: ADMIN_USERTYPE_IDS=1)
    'admin_type_ids' => array_map('intval', array_filter(explode(',', (string) env('ADMIN_USERTYPE_IDS', '')))),
];