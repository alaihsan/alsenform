{{-- The look before anything is painted, so a page never flashes white in the dark: the
     account's choice, else this device's, else the device's own setting. "Ikuti jam" is dark
     from 18.00 until 06.00. Kept in step with resources/js/composables/useAppearance.ts. --}}
<script>
    (function () {
        var root = document.documentElement;
        function stored(key) {
            try {
                return window.localStorage.getItem(key);
            } catch (error) {
                return null;
            }
        }
        if (root.hasAttribute('data-light-only')) {
            return;
        }
        var appearance = root.getAttribute('data-appearance') || stored('appearance') || 'system';
        var colorTheme = root.getAttribute('data-color-theme') || stored('color-theme') || 'standard';
        var hour = new Date().getHours();
        var dark = appearance === 'dark'
            || (appearance === 'schedule' && (hour >= 18 || hour < 6))
            || (appearance === 'system' && !!window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        root.classList[dark ? 'add' : 'remove']('dark');
        if (colorTheme === 'warm') {
            root.setAttribute('data-theme', 'warm');
        } else {
            root.removeAttribute('data-theme');
        }
    })();
</script>
