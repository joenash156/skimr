document.addEventListener('alpine:init', function () {
    Alpine.data('themeToggle', function () {
        return {
            mode: 'system',

            init: function () {
                try {
                    var stored = localStorage.getItem('theme');
                    this.mode = ['light', 'dark', 'system'].indexOf(stored) !== -1 ? stored : 'system';
                } catch (error) {
                    console.warn('Skimr could not read the saved theme preference.', error);
                }

                this.media = window.matchMedia('(prefers-color-scheme: dark)');
                this.media.addEventListener('change', this.apply.bind(this));
                this.apply();
            },

            get label() {
                return document.documentElement.classList.contains('dark')
                    ? this.$el.dataset.lightLabel
                    : this.$el.dataset.darkLabel;
            },

            toggle: function () {
                this.mode = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
                try {
                    localStorage.setItem('theme', this.mode);
                } catch (error) {
                    console.warn('Skimr could not save the theme preference.', error);
                }
                this.apply();
            },

            apply: function () {
                var dark = this.mode === 'dark'
                    || (this.mode === 'system' && this.media.matches);
                document.documentElement.classList.toggle('dark', dark);
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
});
