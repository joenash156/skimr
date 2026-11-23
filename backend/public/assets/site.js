// Public site behaviour (Alpine.js CSP build). Loaded with `defer` before Alpine.
document.addEventListener('alpine:init', function () {
    // Light / dark switch. The inline script in <head> has already applied the saved
    // choice before paint (spec 14.4); "system" follows the OS live until the user picks.
    Alpine.data('themeToggle', function () {
        return {
            init: function () {
                var media = window.matchMedia('(prefers-color-scheme: dark)');
                media.addEventListener('change', function (event) {
                    if (readTheme() === 'system') {
                        document.documentElement.classList.toggle('dark', event.matches);
                    }
                });
            },

            toggle: function () {
                var dark = !document.documentElement.classList.contains('dark');
                document.documentElement.classList.toggle('dark', dark);
                try {
                    localStorage.setItem('theme', dark ? 'dark' : 'light');
                } catch (error) {
                    // Storage blocked (private mode): the choice lasts for this page only.
                }
            }
        };
    });

    Alpine.data('mobileMenu', function () {
        return {
            open: false,

            toggle: function () {
                this.open = !this.open;
            },

            close: function () {
                this.open = false;
            }
        };
    });

    function readTheme() {
        try {
            var stored = localStorage.getItem('theme');
            return stored === 'light' || stored === 'dark' ? stored : 'system';
        } catch (error) {
            return 'system';
        }
    }
});
