{{--
    Page guard: no page stays blank white. Plain ES5 on purpose, so it also runs where the app's
    own scripts cannot (old browsers). It
    - fills in the few newer functions the app uses that older school tablets lack;
    - keeps the app working where the browser refuses localStorage (private mode, blocked cookies);
    - reloads once when a file of the app failed to arrive (Wi-Fi hiccup, the server being updated);
    - otherwise shows what went wrong with a code (App\Support\PageFailure) and reports it to the
      activity report, with the device and browser, so an admin can see who could not open what.
    resources/js/app.ts calls FalconPageGuard.booted() once the page is on screen.
--}}
<style>
    #ff-guard { position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 2147483000; overflow: auto; -webkit-overflow-scrolling: touch; box-sizing: border-box; padding: 32px 16px; background: #f5f3ff; color: #1e1b4b; font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; text-align: left; }
    #ff-guard * { box-sizing: border-box; }
    #ff-guard .ff-card { max-width: 520px; margin: 0 auto; padding: 28px 24px; border-radius: 24px; background: #fff; box-shadow: 0 12px 40px rgba(30, 27, 75, .12); }
    #ff-guard .ff-icon { display: inline-block; width: 48px; height: 48px; border-radius: 16px; background: #fee2e2; color: #b91c1c; font-size: 26px; line-height: 48px; text-align: center; font-weight: 700; }
    #ff-guard .ff-slow .ff-icon { background: #e0e7ff; color: #4338ca; }
    #ff-guard h1 { margin: 16px 0 6px; font-size: 21px; font-weight: 800; line-height: 1.3; color: #0f172a; }
    #ff-guard p { margin: 0 0 12px; color: #334155; }
    #ff-guard .ff-code { display: inline-block; margin: 4px 0 16px; padding: 6px 12px; border-radius: 10px; background: #1e1b4b; color: #fff; font: 700 15px/1.2 Menlo, Consolas, "Courier New", monospace; letter-spacing: .5px; }
    #ff-guard ol { margin: 0 0 18px; padding-left: 20px; list-style: decimal; color: #334155; }
    #ff-guard li { margin-bottom: 6px; }
    #ff-guard .ff-actions a, #ff-guard .ff-actions button { display: inline-block; margin: 0 8px 8px 0; padding: 11px 18px; border: 0; border-radius: 12px; background: #4f46e5; color: #fff; font: 700 15px/1.2 inherit; font-family: inherit; text-decoration: none; cursor: pointer; }
    #ff-guard .ff-actions .ff-secondary { background: #eef2ff; color: #3730a3; }
    #ff-guard .ff-note { margin-top: 8px; font-size: 13px; color: #64748b; }
    #ff-guard .ff-tech { margin-top: 16px; padding: 12px; border-radius: 12px; background: #f8fafc; color: #475569; font: 12px/1.5 Menlo, Consolas, "Courier New", monospace; word-wrap: break-word; overflow-wrap: break-word; }
    #ff-guard .ff-spinner { display: inline-block; width: 16px; height: 16px; margin-right: 8px; vertical-align: -3px; border: 3px solid #c7d2fe; border-top-color: #4f46e5; border-radius: 50%; -webkit-animation: ff-spin 1s linear infinite; animation: ff-spin 1s linear infinite; }
    @-webkit-keyframes ff-spin { to { -webkit-transform: rotate(360deg); } }
    @keyframes ff-spin { to { transform: rotate(360deg); } }
</style>
<script>
    (function () {
        'use strict';

        var codes = @json(\App\Support\PageFailure::catalog());
        var reportUrl = @json(route('page-failures.store', [], false));
        var connectionCheckUrl = @json(route('connection-check', [], false));
        var retryKey = 'ff-page-retry';
        var state = { booted: false, shown: null, reported: {}, timer: null, reloading: false, fileFailing: false };
        var now = function () { return new Date().getTime(); };
        var start = now();

        /* Newer functions the app (or Vite and its libraries) uses, for browsers that lack them. */
        var define = function (target, name, value) {
            if (target && !target[name]) {
                try {
                    Object.defineProperty(target, name, { value: value, configurable: true, writable: true, enumerable: false });
                } catch (error) {
                    target[name] = value;
                }
            }
        };
        if (typeof window.globalThis === 'undefined') {
            window.globalThis = window;
        }
        define(window, 'queueMicrotask', function (callback) {
            Promise.resolve().then(callback)['catch'](function (error) {
                setTimeout(function () { throw error; });
            });
        });
        if (window.Promise) {
            define(Promise, 'allSettled', function (items) {
                return Promise.all(Array.prototype.map.call(Array.from(items), function (item) {
                    return Promise.resolve(item).then(function (value) {
                        return { status: 'fulfilled', value: value };
                    }, function (reason) {
                        return { status: 'rejected', reason: reason };
                    });
                }));
            });
        }
        var at = function (index) {
            var length = this.length >>> 0;
            var position = Math.trunc ? Math.trunc(index) || 0 : parseInt(index, 10) || 0;
            position = position < 0 ? position + length : position;
            return position < 0 || position >= length ? undefined : this[position];
        };
        define(Array.prototype, 'at', at);
        define(String.prototype, 'at', at);
        define(Object, 'fromEntries', function (entries) {
            var result = {};
            Array.from(entries).forEach(function (entry) { result[entry[0]] = entry[1]; });
            return result;
        });
        define(Object, 'hasOwn', function (object, key) {
            return Object.prototype.hasOwnProperty.call(object, key);
        });
        if (!window.ResizeObserver) {
            /* Enough for the app's menus: one report after observing, and again when the window resizes. */
            window.ResizeObserver = function (callback) {
                var observer = this;
                var targets = [];
                var timer = null;
                var notify = function () {
                    var entries = targets.map(function (target) {
                        var box = target.getBoundingClientRect();
                        var size = [{ inlineSize: box.width, blockSize: box.height }];
                        return { target: target, contentRect: box, borderBoxSize: size, contentBoxSize: size };
                    });
                    if (entries.length) {
                        callback(entries, observer);
                    }
                };
                var schedule = function () {
                    clearTimeout(timer);
                    timer = setTimeout(notify, 50);
                };
                this.observe = function (target) {
                    if (targets.indexOf(target) < 0) {
                        targets.push(target);
                    }
                    schedule();
                };
                this.unobserve = function (target) {
                    var index = targets.indexOf(target);
                    if (index >= 0) {
                        targets.splice(index, 1);
                    }
                };
                this.disconnect = function () {
                    targets = [];
                    window.removeEventListener('resize', schedule);
                };
                window.addEventListener('resize', schedule);
            };
        }

        /* Storage the browser refuses (private mode, blocked cookies) is kept in memory instead. */
        var storageWorks = function (name) {
            try {
                var storage = window[name];
                storage.setItem('ff-test', '1');
                storage.removeItem('ff-test');
                return true;
            } catch (error) {
                return false;
            }
        };
        var memoryStorage = function () {
            var data = {};
            return {
                getItem: function (key) { return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null; },
                setItem: function (key, value) { data[key] = String(value); },
                removeItem: function (key) { delete data[key]; },
                clear: function () { data = {}; },
                key: function (index) { return Object.keys(data)[index] || null; },
                get length() { return Object.keys(data).length; }
            };
        };
        var canRetry = storageWorks('sessionStorage');
        ['localStorage', 'sessionStorage'].forEach(function (name) {
            if (!storageWorks(name)) {
                try {
                    Object.defineProperty(window, name, { value: memoryStorage(), configurable: true, writable: true });
                } catch (error) {
                    /* Nothing better to do: the app's own guards take over. */
                }
            }
        });

        /* What the app needs and cannot be filled in: ES modules, Proxy, AbortController, IntersectionObserver. */
        var runsModules = 'noModule' in document.createElement('script');
        var isOldBrowser = !runsModules || typeof window.Proxy !== 'function' || typeof window.Promise !== 'function'
            || typeof window.AbortController !== 'function' || typeof window.IntersectionObserver !== 'function';

        var onReady = function (callback) {
            if (document.body) {
                callback();
            } else {
                document.addEventListener('DOMContentLoaded', callback);
            }
        };
        var afterLoad = function (callback) {
            var later = function () { setTimeout(callback, 1500); };
            if (document.readyState === 'complete') {
                later();
            } else {
                window.addEventListener('load', later);
            }
        };
        var escape = function (text) {
            return String(text).replace(/[&<>"']/g, function (character) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
            });
        };

        var report = function (code, detail) {
            if (state.reported[code]) {
                return;
            }
            state.reported[code] = true;
            var body = ['code=' + encodeURIComponent(code),
                'path=' + encodeURIComponent((location.pathname + location.search).slice(0, 300)),
                'message=' + encodeURIComponent(String(detail.message || '').slice(0, 300)),
                'asset=' + encodeURIComponent(String(detail.asset || '').slice(0, 300))].join('&');
            try {
                if (navigator.sendBeacon && window.Blob && navigator.sendBeacon(reportUrl, new Blob([body], { type: 'application/x-www-form-urlencoded' }))) {
                    return;
                }
            } catch (error) {
                /* Fall back to a plain request below. */
            }
            try {
                var request = new XMLHttpRequest();
                request.open('POST', reportUrl, true);
                request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                request.send(body);
            } catch (error) {
                /* Reporting is a courtesy: the code is on the screen anyway. */
            }
        };

        var hide = function () {
            var panel = document.getElementById('ff-guard');
            if (panel && panel.parentNode) {
                panel.parentNode.removeChild(panel);
            }
            state.shown = null;
        };

        var show = function (code, detail) {
            var failure = codes[code];
            if (!failure || state.shown === code || (state.booted && code !== 'FF-302')) {
                return;
            }
            hide();
            state.shown = code;
            var slow = code === 'FF-401';
            var steps = failure.steps.map(function (step) { return '<li>' + escape(step) + '</li>'; }).join('');
            var technical = [
                'Kode: ' + code,
                'Waktu: ' + new Date().toLocaleString(),
                'Alamat: ' + location.href,
                detail.asset ? 'Berkas: ' + detail.asset : '',
                detail.message ? 'Pesan: ' + detail.message : '',
                'Browser: ' + navigator.userAgent
            ].filter(Boolean).map(escape).join('<br>');
            var panel = document.createElement('div');
            panel.id = 'ff-guard';
            panel.setAttribute('role', 'alert');
            panel.setAttribute('data-code', code);
            panel.innerHTML = '<div class="ff-card' + (slow ? ' ff-slow' : '') + '">'
                + '<span class="ff-icon" aria-hidden="true">' + (slow ? '&#8987;' : '!') + '</span>'
                + '<h1>' + (slow ? '<span class="ff-spinner" aria-hidden="true"></span>' : '') + escape(failure.title) + '</h1>'
                + '<p>' + escape(failure.text) + '</p>'
                + '<span class="ff-code">' + escape(code) + '</span>'
                + '<ol>' + steps + '</ol>'
                + '<div class="ff-actions"><button type="button" data-action="reload">Muat ulang</button>'
                + (code === 'FF-201' || code === 'FF-401' ? '<a class="ff-secondary" href="' + escape(connectionCheckUrl) + '">Cek Koneksi</a>' : '')
                + '</div>'
                + '<p class="ff-note">Sebutkan kode <strong>' + escape(code) + '</strong> kepada guru atau admin. Kejadian ini juga tercatat di Laporan Aktivitas.</p>'
                + '<div class="ff-tech">' + technical + '</div>'
                + '</div>';
            panel.querySelector('[data-action="reload"]').onclick = function () { location.reload(); };
            document.body.appendChild(panel);
        };

        var fail = function (code, detail) {
            if (state.reloading || (state.booted && code !== 'FF-302')) {
                return;
            }
            /* A failure on a browser older than the app needs: that is the reason to tell. */
            var shownCode = isOldBrowser && code !== 'FF-201' && code !== 'FF-202' ? 'FF-102' : code;
            report(shownCode, detail || {});
            onReady(function () { show(shownCode, detail || {}); });
        };

        /* One automatic reload per minute: it fixes a file lost to a Wi-Fi hiccup or a page from before an update. */
        var reloadOnce = function () {
            if (!canRetry) {
                return false;
            }
            try {
                if (now() - Number(sessionStorage.getItem(retryKey) || 0) < 60000) {
                    return false;
                }
                sessionStorage.setItem(retryKey, String(now()));
            } catch (error) {
                return false;
            }
            state.reloading = true;
            location.reload();
            return true;
        };

        var isAppFile = function (url) {
            return /\/build\//.test(url || '');
        };
        var fileIn = function (message) {
            var match = /(https?:\/\/[^\s'"]+|\/build\/[^\s'"]+)/.exec(message || '');
            return match ? match[1] : '';
        };

        /* A file of the app did not arrive: missing on the server (404) or the connection failed. */
        var fileFailed = function (url, message) {
            /* Once per page: the app's script also fails as its modulepreload link. */
            if (state.booted || state.fileFailing) {
                return;
            }
            state.fileFailing = true;
            var decide = function (status) {
                if (reloadOnce()) {
                    return;
                }
                fail(status === 404 || status === 410 ? 'FF-202' : 'FF-201', { asset: url, message: message || (status ? 'HTTP ' + status : 'Server tidak menjawab') });
            };
            if (!url) {
                decide(0);
                return;
            }
            try {
                var probe = new XMLHttpRequest();
                probe.open('HEAD', url, true);
                probe.timeout = 8000;
                probe.onload = function () { decide(probe.status); };
                probe.onerror = probe.ontimeout = function () { decide(0); };
                probe.send();
            } catch (error) {
                decide(0);
            }
        };

        var startFailed = function (message) {
            afterLoad(function () {
                if (!state.booted) {
                    fail('FF-301', { message: message });
                }
            });
        };

        var isLoadError = function (message) {
            return /dynamically imported module|Importing a module script failed|error loading dynamically imported module|Unable to preload|Failed to fetch/i.test(message || '');
        };

        window.addEventListener('error', function (event) {
            var target = event.target;
            if (target && target !== window && target.tagName) {
                var url = target.src || target.href || '';
                if ((target.tagName === 'SCRIPT' || target.tagName === 'LINK') && isAppFile(url)) {
                    fileFailed(url, '');
                }
                return;
            }
            var message = event.message || (event.error && event.error.message) || '';
            /* Errors of other sites' scripts (browser extensions) say nothing about the app. */
            if (state.booted || message === 'Script error.' || (event.filename && event.filename.indexOf(location.protocol + '//' + location.host) !== 0)) {
                return;
            }
            var place = event.filename ? ' (' + event.filename.split('/').pop() + ':' + event.lineno + ':' + event.colno + ')' : '';
            startFailed(message + place);
        }, true);

        window.addEventListener('unhandledrejection', function (event) {
            if (state.booted) {
                return;
            }
            var reason = event.reason;
            var message = reason && reason.message ? reason.message : String(reason);
            if (isLoadError(message)) {
                fileFailed(fileIn(message), message);
            } else {
                startFailed(message);
            }
        });

        /* A part of a page could not be fetched before the page was on screen (often a page from before
           an update): reload once. Once the app runs, it handles its own parts, without reloading a page
           a teacher may be typing in. */
        window.addEventListener('vite:preloadError', function (event) {
            if (!state.booted && reloadOnce() && event.preventDefault) {
                event.preventDefault();
            }
        });

        window.FalconPageGuard = {
            booted: function () {
                state.booted = true;
                clearTimeout(state.timer);
                if (state.shown !== 'FF-302') {
                    hide();
                }
            },
            failed: function (code, message) {
                fail(code, { message: message });
            }
        };

        if (!runsModules) {
            fail('FF-102', { message: 'Browser tidak menjalankan ES module' });
        } else {
            /* Still nothing after 20 seconds: say so, and keep waiting (the page replaces this message). */
            state.timer = setTimeout(function () {
                if (!state.booted && !state.shown) {
                    fail('FF-401', { message: 'Belum tampil setelah ' + Math.round((now() - start) / 1000) + ' detik' });
                }
            }, 20000);
        }
    })();
</script>
