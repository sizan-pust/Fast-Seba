(function () {
    'use strict';

    const themeKey = 'fastsheba-admin-theme';

    function currentTheme() {
        return localStorage.getItem(themeKey)
            || document.documentElement.getAttribute('data-bs-theme')
            || 'light';
    }

    function applyTheme(theme) {
        localStorage.setItem(themeKey, theme);

        if (theme === 'dark') {
            document.documentElement.setAttribute(
                'data-bs-theme',
                'dark'
            );
        } else {
            document.documentElement.removeAttribute(
                'data-bs-theme'
            );
        }

        document
            .querySelectorAll('[data-theme-icon]')
            .forEach(function (icon) {
                const target = icon.getAttribute(
                    'data-theme-icon'
                );

                icon.classList.toggle(
                    'd-none',
                    target === theme
                );
            });
    }

    document.addEventListener(
        'DOMContentLoaded',
        function () {
            applyTheme(currentTheme());

            const toggle = document.getElementById(
                'admin-theme-toggle'
            );

            if (toggle) {
                toggle.addEventListener(
                    'click',
                    function () {
                        applyTheme(
                            currentTheme() === 'dark'
                                ? 'light'
                                : 'dark'
                        );
                    }
                );
            }

            document
                .querySelectorAll('[data-password-toggle]')
                .forEach(function (button) {
                    button.addEventListener(
                        'click',
                        function () {
                            const input = document.querySelector(
                                button.getAttribute(
                                    'data-password-toggle'
                                )
                            );

                            if (! input) {
                                return;
                            }

                            const show =
                                input.type === 'password';

                            input.type = show
                                ? 'text'
                                : 'password';

                            button.textContent = show
                                ? 'Hide'
                                : 'Show';
                        }
                    );
                });
        }
    );
})();
