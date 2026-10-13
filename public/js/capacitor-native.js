/**
 * Agor mobile polish helpers.
 *
 * Pull-to-refresh runs on any touch device (Capacitor APK + mobile browsers).
 * Everything below the native guard is APK-only and no-ops in regular
 * browsers, so the rest of the web version is unaffected.
 */
(function () {
    // ---------- Pull-to-refresh (touch devices: APK + mobile browsers) ----------
    // Pulling down while the page is scrolled to the top reloads the page.
    // Pages with a visible data-entry form are skipped so unsaved input is
    // never lost.
    function setupPullToRefresh() {
        var TRIGGER_DISTANCE = 60;
        var MAX_PULL = 100;
        var DAMPING = 0.45;

        var indicator = null;
        var arrow = null;
        var spinner = null;
        var label = null;

        var state = { tracking: false, startX: 0, startY: 0, pulling: false, distance: 0, refreshing: false };

        function pageHasVisibleDataEntryForm() {
            var forms = document.querySelectorAll('form');

            for (var i = 0; i < forms.length; i++) {
                var fields = forms[i].querySelectorAll('input, select, textarea');

                for (var j = 0; j < fields.length; j++) {
                    var field = fields[j];
                    var type = (field.getAttribute('type') || 'text').toLowerCase();

                    if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset') {
                        continue;
                    }

                    if (field.offsetParent !== null && field.getClientRects().length > 0) {
                        return true;
                    }
                }
            }

            return false;
        }

        function ensureIndicator() {
            if (indicator) {
                return;
            }

            var style = document.createElement('style');
            style.textContent = '@keyframes capRefreshSpin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);

            arrow = document.createElement('div');
            arrow.style.cssText = 'width:24px;height:24px;line-height:24px;text-align:center;' +
                'font-size:18px;font-weight:bold;color:#047857;';

            spinner = document.createElement('div');
            spinner.style.cssText = 'display:none;width:22px;height:22px;border:3px solid #d1fae5;' +
                'border-top-color:#047857;border-radius:50%;animation:capRefreshSpin 0.7s linear infinite;';

            label = document.createElement('div');
            label.style.cssText = 'margin-top:4px;font-size:12px;color:#047857;font-family:system-ui,sans-serif;';

            var content = document.createElement('div');
            content.style.cssText = 'display:flex;flex-direction:column;align-items:center;' +
                'justify-content:flex-end;padding-bottom:10px;';
            content.appendChild(arrow);
            content.appendChild(spinner);
            content.appendChild(label);

            indicator = document.createElement('div');
            indicator.id = 'capacitor-pull-refresh';
            indicator.style.cssText = 'position:fixed;top:0;left:0;right:0;height:0;z-index:9997;' +
                'overflow:hidden;display:flex;align-items:flex-end;justify-content:center;' +
                'background:rgba(255,255,255,0.95);box-shadow:0 1px 4px rgba(0,0,0,0.08);' +
                'pointer-events:none;';
            indicator.appendChild(content);
            document.body.appendChild(indicator);
        }

        function renderPulling() {
            var progress = Math.min(1, state.distance / TRIGGER_DISTANCE);

            indicator.style.height = state.distance + 'px';
            arrow.style.display = 'block';
            arrow.textContent = '\u2193';
            arrow.style.transform = 'rotate(' + (progress * 180) + 'deg)';
            spinner.style.display = 'none';
            label.textContent = state.distance >= TRIGGER_DISTANCE ?
                'রিফ্রেশ করতে ছেড়ে দিন' : 'রিফ্রেশ করতে টানুন';
        }

        function renderRefreshing() {
            indicator.style.height = '56px';
            arrow.style.display = 'none';
            spinner.style.display = 'block';
            label.textContent = 'রিফ্রেশ হচ্ছে…';
        }

        function collapseIndicator() {
            if (!indicator) {
                return;
            }

            indicator.style.transition = 'height 0.2s ease';
            indicator.style.height = '0';
            setTimeout(function () {
                indicator.style.transition = 'none';
            }, 220);
        }

        function resetGesture() {
            state.tracking = false;
            state.pulling = false;
            state.distance = 0;
        }

        document.addEventListener('touchstart', function (e) {
            if (state.refreshing || e.touches.length !== 1 || window.scrollY > 0 || pageHasVisibleDataEntryForm()) {
                resetGesture();
                return;
            }

            state.tracking = true;
            state.startX = e.touches[0].clientX;
            state.startY = e.touches[0].clientY;
            state.pulling = false;
            state.distance = 0;
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!state.tracking || state.refreshing || e.touches.length !== 1 || window.scrollY > 0) {
                return;
            }

            var deltaY = e.touches[0].clientY - state.startY;
            var deltaX = e.touches[0].clientX - state.startX;

            if (!state.pulling) {
                if (deltaY > 8 && deltaY > Math.abs(deltaX)) {
                    state.pulling = true;
                    ensureIndicator();
                } else {
                    return;
                }
            }

            if (deltaY <= 0) {
                collapseIndicator();
                resetGesture();
                return;
            }

            e.preventDefault();
            state.distance = Math.min(MAX_PULL, deltaY * DAMPING);
            renderPulling();
        }, { passive: false });

        function finishPull() {
            if (!state.tracking || !state.pulling) {
                return;
            }

            if (state.distance >= TRIGGER_DISTANCE) {
                state.refreshing = true;
                renderRefreshing();
                window.location.reload();
                return;
            }

            collapseIndicator();
            resetGesture();
        }

        document.addEventListener('touchend', finishPull);
        document.addEventListener('touchcancel', finishPull);
    }

    var isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());

    if ('ontouchstart' in window) {
        setupPullToRefresh();
    }

    // Bail out in normal browsers — the native plugins below only exist in the Capacitor WebView.
    if (!isNative) {
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
