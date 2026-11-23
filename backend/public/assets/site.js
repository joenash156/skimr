// Public site behaviour (Alpine.js CSP build). Loaded with `defer` before Alpine.
// The inline script in <head> has already applied the saved theme before paint (spec 14.4).
(function () {
    var media = window.matchMedia('(prefers-color-scheme: dark)');

    function readMode() {
        try {
            var stored = localStorage.getItem('theme');
            return stored === 'light' || stored === 'dark' ? stored : 'system';
        } catch (error) {
            return 'system';
        }
    }

    function saveMode(mode) {
        try {
            if (mode === 'system') {
                localStorage.removeItem('theme');
            } else {
                localStorage.setItem('theme', mode);
            }
        } catch (error) {
            // Storage blocked (private mode): the choice lasts for this page only.
        }
    }

    function apply(mode) {
        var dark = mode === 'dark' || (mode === 'system' && media.matches);
        document.documentElement.classList.toggle('dark', dark);
    }

    // "System" follows the OS live (spec 14.4).
    media.addEventListener('change', function () {
        if (readMode() === 'system') {
            apply('system');
        }
    });

    document.addEventListener('alpine:init', function () {
        // Light / Dark / System radio group. Several can be on a page (header, phone
        // menu, footer); a window event keeps them all showing the same choice.
        Alpine.data('themeSwitch', function () {
            return {
                init: function () {
                    var root = this.$el;
                    var sync = function (mode) {
                        root.querySelectorAll('input[type="radio"]').forEach(function (input) {
                            input.checked = input.value === mode;
                        });
                    };
                    sync(readMode());
                    window.addEventListener('skimr:theme', function (event) {
                        sync(event.detail);
                    });
                },

                pick: function (event) {
                    var mode = event.target.value;
                    saveMode(mode);
                    apply(mode);
                    window.dispatchEvent(new CustomEvent('skimr:theme', { detail: mode }));
                }
            };
        });

        // Simple disclosure menu (language switcher).
        Alpine.data('dropdown', function () {
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

        // Home hero carousel: cross-fades the photos and headings. Pauses while hovered or
        // focused, has a pause button (WCAG 2.2.2), and never auto-plays with reduced motion.
        Alpine.data('heroCarousel', function () {
            return {
                index: 0,
                timer: null,
                paused: false,
                holding: false,

                init: function () {
                    this.images = this.$el.querySelectorAll('[data-slide-image]');
                    this.headings = this.$el.querySelectorAll('[data-slide-heading]');
                    this.dots = this.$el.querySelectorAll('.hero__dot');
                    this.pauseButton = this.$el.querySelector('.hero__pause');
                    this.interval = Number(this.$el.dataset.interval) || 6500;
                    if (this.images.length < 2) {
                        return;
                    }
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        this.paused = true;
                        this.$el.classList.add('is-static');
                    }
                    this.schedule();
                },

                schedule: function () {
                    clearTimeout(this.timer);
                    if (this.paused || this.holding) {
                        return;
                    }
                    var self = this;
                    this.timer = setTimeout(function () {
                        self.show((self.index + 1) % self.images.length);
                        self.schedule();
                    }, this.interval);
                },

                show: function (next) {
                    this.index = next;
                    var toggle = function (list) {
                        list.forEach(function (element, i) {
                            element.classList.toggle('is-active', i === next);
                        });
                    };
                    toggle(this.images);
                    toggle(this.headings);
                    toggle(this.dots);
                    this.headings.forEach(function (heading, i) {
                        if (i === next) {
                            heading.removeAttribute('aria-hidden');
                        } else {
                            heading.setAttribute('aria-hidden', 'true');
                        }
                    });
                    this.dots.forEach(function (dot, i) {
                        if (i === next) {
                            dot.setAttribute('aria-current', 'true');
                        } else {
                            dot.removeAttribute('aria-current');
                        }
                    });
                },

                go: function (event) {
                    this.show(Number(event.currentTarget.dataset.index));
                    this.restartProgress();
                    this.schedule();
                },

                togglePause: function () {
                    this.paused = !this.paused;
                    this.$el.classList.toggle('is-paused', this.paused);
                    var label = this.paused ? this.pauseButton.dataset.labelPlay : this.pauseButton.dataset.labelPause;
                    this.pauseButton.setAttribute('aria-label', label);
                    this.restartProgress();
                    this.schedule();
                },

                hold: function () {
                    this.holding = true;
                    clearTimeout(this.timer);
                    this.$el.classList.add('is-holding');
                },

                release: function () {
                    this.holding = false;
                    this.$el.classList.remove('is-holding');
                    this.restartProgress();
                    this.schedule();
                },

                // Restart the active pill's fill so it matches the fresh timer.
                restartProgress: function () {
                    var active = this.dots[this.index];
                    if (!active) {
                        return;
                    }
                    active.classList.remove('is-active');
                    void active.offsetWidth;
                    active.classList.add('is-active');
                }
            };
        });
    });

    // Scroll reveal: sections below the fold fade up as they come into view.
    // Items already on screen are left alone, so nothing flickers on load.
    document.addEventListener('DOMContentLoaded', function () {
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px' });

        var groups = '.steps, .grid, .grid--4, .ruled-grid, .faq, .flow, .principles, .topic-list';
        var singles = '.section__head, .cta, .contact__card, .about-mission, .tier__head, .legal__section';
        var fold = window.innerHeight;
        var watch = function (element, i) {
            if (element.getBoundingClientRect().top < fold) {
                return;
            }
            element.style.setProperty('--reveal-i', String(i % 6));
            element.classList.add('reveal');
            observer.observe(element);
        };
        document.querySelectorAll(groups).forEach(function (group) {
            Array.prototype.forEach.call(group.children, watch);
        });
        document.querySelectorAll(singles).forEach(function (element) {
            watch(element, 0);
        });
    });
})();
