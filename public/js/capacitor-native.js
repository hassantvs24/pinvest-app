/**
 * Agor Android (Capacitor) native feel helpers.
 *
 * This file is loaded ONLY inside the Capacitor WebView (the APK) — it no-ops
 * in regular browsers, so the web version is completely unaffected.
 */
(function () {
    // Bail out in normal browsers — these globals only exist in the Capacitor WebView.
    if (!window.Capacitor || !window.Capacitor.isNativePlatform || !window.Capacitor.isNativePlatform()) {
        return;
    }

    var Capacitor = window.Capacitor;
    var Plugins = Capacitor.Plugins || {};
    var StatusBar = Plugins.StatusBar;
    var SplashScreen = Plugins.SplashScreen;
    var Keyboard = Plugins.Keyboard;
    var App = Plugins.App;
    var Network = Plugins.Network;

    // ---------- Status bar: blend with the emerald header (#047857) ----------
    if (StatusBar) {
        StatusBar.setBackgroundColor({ color: '#047857' }).catch(function () {});
        StatusBar.setStyle({ style: 'LIGHT' }).catch(function () {});
    }

    // ---------- Splash screen: make sure it always hides ----------
    if (SplashScreen) {
        window.addEventListener('load', function () {
            setTimeout(function () {
                SplashScreen.hide().catch(function () {});
            }, 300);
        });
    }

    // ---------- Keyboard: keep focused input visible (form-heavy app) ----------
    if (Keyboard) {
        Keyboard.setAccessoryBarVisible({ isVisible: false }).catch(function () {});
    }

    // ---------- Hardware back button: history back, confirm before exit ----------
    if (App) {
        var lastBackPress = 0;

        App.addListener('backButton', function () {
            // A confirm()/alert() is open — let Android close it.
            if (window.confirmOpen) {
                return;
            }

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            // No history left: double-tap to exit, native toast style.
            var now = Date.now();
            if (now - lastBackPress < 2000) {
                App.exitApp();
            } else {
                lastBackPress = now;
                if (Plugins.Toast) {
                    Plugins.Toast.show({ text: 'আবার চাপলে অ্যাপ বন্ধ হবে', duration: 'short' }).catch(function () {});
                }
            }
        });
    }

    // ---------- Page transition loader (top indeterminate progress bar) ----------
    // Pages are full server-side loads, so show the bar on internal link clicks
    // and form submits, and hide it once the next page has loaded.
    var loader = null;

    function ensureLoader() {
        if (loader) {
            return loader;
        }

        var style = document.createElement('style');
        style.textContent = '@keyframes capLoaderSlide{0%{margin-left:-40%}100%{margin-left:100%}}';
        document.head.appendChild(style);

        var inner = document.createElement('div');
        inner.style.cssText = 'height:100%;width:40%;background:#fbbf24;border-radius:2px;' +
            'animation:capLoaderSlide 0.9s ease-in-out infinite;';

        loader = document.createElement('div');
        loader.id = 'capacitor-page-loader';
        loader.style.cssText = 'position:fixed;left:0;right:0;top:env(safe-area-inset-top,0px);' +
            'height:3px;z-index:9998;overflow:hidden;display:none;';
        loader.appendChild(inner);
        document.body.appendChild(loader);

        return loader;
    }

    function showLoader() {
        ensureLoader().style.display = 'block';
    }

    function hideLoader() {
        if (loader) {
            loader.style.display = 'none';
        }
    }

    // Bind after window load so our document-level listeners run AFTER jQuery's
    // confirm handlers — that way canceled submits (confirm() = false) never
    // trigger the bar (jQuery's preventDefault sets defaultPrevented).
    window.addEventListener('load', function () {
        hideLoader();

        document.addEventListener('click', function (e) {
            if (e.defaultPrevented) {
                return;
            }

            var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!link) {
                return;
            }

            var href = link.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 ||
                link.hasAttribute('download') || link.getAttribute('target') === '_blank') {
                return;
            }

            showLoader();
        });

        document.addEventListener('submit', function (e) {
            if (!e.defaultPrevented) {
                showLoader();
            }
        });
    });

    // Hardware-back navigations also fire pageshow/load — hide there too.
    window.addEventListener('pageshow', hideLoader);

    // ---------- Offline banner ----------
    if (Network) {
        var banner = null;

        function renderBanner(offline) {
            if (offline && !banner) {
                banner = document.createElement('div');
                banner.id = 'capacitor-offline-banner';
                banner.textContent = '⚠️ ইন্টারনেট সংযোগ নেই';
                banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;' +
                    'background:#b91c1c;color:#fff;text-align:center;font-size:13px;' +
                    'padding:6px 8px;font-family:system-ui,sans-serif;';
                document.body.appendChild(banner);
            } else if (!offline && banner) {
                banner.remove();
                banner = null;
            }
        }

        Network.getStatus().then(function (s) { renderBanner(!s.connected); }).catch(function () {});
        Network.addListener('networkStatusChange', function (s) { renderBanner(!s.connected); });
    }
})();
