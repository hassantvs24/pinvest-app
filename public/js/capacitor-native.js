/**
 * Pinvest Android (Capacitor) native feel helpers.
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
