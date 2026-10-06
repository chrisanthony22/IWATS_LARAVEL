{{-- resources/views/layouts/theme-init.blade.php  — include inside <head> of every layout --}}
<script>
    (function () {
        var t = 'blue';
        try { t = localStorage.getItem('theme') || 'blue'; } catch (e) {}
        document.documentElement.setAttribute('data-theme', t);
    })();
    window.toggleTheme = function () {
        var t = document.documentElement.getAttribute('data-theme') === 'light' ? 'blue' : 'light';
        document.documentElement.setAttribute('data-theme', t);
        try { localStorage.setItem('theme', t); } catch (e) {}
        window.dispatchEvent(new Event('themechange'));
        return t;
    };
</script>