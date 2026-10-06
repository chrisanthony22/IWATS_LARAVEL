{{-- resources/views/components/page-loader.blade.php  — add <x-page-loader /> once per layout --}}
<div id="page-loader" role="status" aria-live="polite" aria-label="Loading"
     style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;
            background:rgba(15,23,42,.45);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px)">
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;padding:1.5rem 2.25rem;border-radius:1rem;
                background:var(--card,rgba(255,255,255,.15));border:1px solid var(--card-border,rgba(255,255,255,.25));
                color:var(--text,#fff);box-shadow:0 20px 40px rgba(0,0,0,.25)">
        <div class="pl-spinner"></div>
        <p id="page-loader-text" style="margin:0;font-size:.875rem;font-weight:600">Loading…</p>
    </div>
</div>

<style>
    .pl-spinner { width: 3rem; height: 3rem; border-radius: 9999px;
                  border: 4px solid rgba(148,163,184,.35); border-top-color: var(--accent, #bae6fd);
                  animation: pl-spin .8s linear infinite; }
    #page-loader.pl-on { animation: pl-fade .15s ease-out; }
    @keyframes pl-spin { to { transform: rotate(360deg); } }
    @keyframes pl-fade { from { opacity: 0; } to { opacity: 1; } }
</style>

<script>
    (function () {
        var el = document.getElementById('page-loader'),
            txt = document.getElementById('page-loader-text'),
            count = 0, timer;

        window.Loader = {
            show: function (msg) {
                count++;
                txt.textContent = msg || 'Loading…';
                el.style.display = 'flex';
                el.classList.add('pl-on');
                clearTimeout(timer);
                timer = setTimeout(window.Loader.reset, 30000);   // failsafe: never get stuck (e.g. file downloads)
            },
            hide: function () {
                count = Math.max(0, count - 1);
                if (!count) window.Loader.reset();
            },
            reset: function () {
                count = 0; clearTimeout(timer);
                el.style.display = 'none';
                el.classList.remove('pl-on');
            },
            // wrap any fetch/axios call:  Loader.track(fetch(url)).then(...)
            track: function (promise, msg) {
                window.Loader.show(msg);
                return Promise.resolve(promise).finally(function () { window.Loader.hide(); });
            }
        };

        // 1) every normal form submit (skip with data-no-loader)
        document.addEventListener('submit', function (e) {
            var f = e.target;
            if (e.defaultPrevented || f.hasAttribute('data-no-loader') || f.target === '_blank') return;
            window.Loader.show(f.getAttribute('data-loader-text'));
        });

        // 2) every normal link click to another page of this site (skip with data-no-loader)
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-loader')) return;
            var h = a.getAttribute('href') || '';
            if (!h || h.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(h)) return;
            if (a.origin !== location.origin) return;
            window.Loader.show();
        });

        // 3) coming back with the browser Back button: don't leave the spinner on
        window.addEventListener('pageshow', function (e) { if (e.persisted) window.Loader.reset(); });
    })();
</script>