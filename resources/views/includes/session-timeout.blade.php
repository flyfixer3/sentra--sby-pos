@auth
    @php
        $timeoutMinutes = (int) config('session.inactivity_timeout', config('session.lifetime', 120));
        $warningMinutes = (int) config('session.inactivity_warning', 1);
    @endphp

    @if ($timeoutMinutes > 0)
        <div class="modal fade" id="session-timeout-warning" tabindex="-1" role="dialog" aria-labelledby="session-timeout-warning-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="session-timeout-warning-title">Session Expiring Soon</h5>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            Your session will expire soon because there has been no activity.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="session-timeout-logout">
                            Logout
                        </button>
                        <button type="button" class="btn btn-primary" id="session-timeout-stay">
                            Stay Logged In
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <form id="session-timeout-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>

        <script>
            (function () {
                var timeoutMs = {{ $timeoutMinutes }} * 60 * 1000;
                var warningMs = Math.min({{ $warningMinutes }} * 60 * 1000, Math.max(timeoutMs - 1000, 0));
                var warningAtMs = Math.max(timeoutMs - warningMs, 0);
                var keepAliveUrl = @json(route('session.keep-alive'));
                var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                var storageKey = 'crm:last-activity-at';
                var logoutKey = 'crm:session-timeout-logout-at';
                var lastActivityAt = Date.now();
                var lastKeepAliveAt = Date.now();
                var warningTimer = null;
                var logoutTimer = null;
                var activityThrottle = null;
                var keepAliveInterval = Math.max(Math.min(timeoutMs / 2, 5 * 60 * 1000), 30 * 1000);

                function getLastActivityAt() {
                    var stored = parseInt(localStorage.getItem(storageKey), 10);

                    return Number.isFinite(stored) ? stored : Date.now();
                }

                function setLastActivityAt(value) {
                    lastActivityAt = value;
                    localStorage.setItem(storageKey, String(value));
                }

                function showWarning() {
                    if (window.jQuery && jQuery.fn.modal) {
                        jQuery('#session-timeout-warning').modal({
                            backdrop: 'static',
                            keyboard: false,
                            show: true
                        });
                    } else {
                        alert('Your session will expire soon because there has been no activity.');
                    }
                }

                function hideWarning() {
                    if (window.jQuery && jQuery.fn.modal) {
                        jQuery('#session-timeout-warning').modal('hide');
                    }
                }

                function submitLogout() {
                    localStorage.setItem(logoutKey, String(Date.now()));
                    document.getElementById('session-timeout-logout-form').submit();
                }

                function sendKeepAlive(force) {
                    var now = Date.now();

                    if (! force && now - lastKeepAliveAt < keepAliveInterval) {
                        return Promise.resolve();
                    }

                    lastKeepAliveAt = now;

                    return fetch(keepAliveUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ keep_alive: true })
                    }).then(function (response) {
                        if (response.status === 401 || response.status === 419) {
                            submitLogout();
                        }
                    }).catch(function () {
                        // A temporary network failure should not immediately log out an active user.
                    });
                }

                function clearTimers() {
                    clearTimeout(warningTimer);
                    clearTimeout(logoutTimer);
                }

                function scheduleTimers() {
                    var idleFor = Date.now() - getLastActivityAt();
                    var warningDelay = Math.max(warningAtMs - idleFor, 0);
                    var logoutDelay = Math.max(timeoutMs - idleFor, 0);

                    clearTimers();

                    if (logoutDelay <= 0) {
                        submitLogout();
                        return;
                    }

                    if (idleFor >= warningAtMs) {
                        showWarning();
                    } else {
                        warningTimer = setTimeout(showWarning, warningDelay);
                    }

                    logoutTimer = setTimeout(submitLogout, logoutDelay);
                }

                function recordActivity() {
                    if (activityThrottle) {
                        return;
                    }

                    activityThrottle = setTimeout(function () {
                        activityThrottle = null;
                    }, 1000);

                    setLastActivityAt(Date.now());
                    hideWarning();
                    scheduleTimers();
                    sendKeepAlive(false);
                }

                ['click', 'keydown', 'mousedown', 'mousemove', 'scroll', 'touchstart'].forEach(function (eventName) {
                    window.addEventListener(eventName, recordActivity, { passive: true });
                });

                document.addEventListener('visibilitychange', function () {
                    if (! document.hidden) {
                        scheduleTimers();
                        sendKeepAlive(false);
                    }
                });

                window.addEventListener('storage', function (event) {
                    if (event.key === storageKey) {
                        scheduleTimers();
                    }

                    if (event.key === logoutKey) {
                        window.location.href = @json(route('login'));
                    }
                });

                document.getElementById('session-timeout-stay').addEventListener('click', function () {
                    setLastActivityAt(Date.now());
                    hideWarning();
                    scheduleTimers();
                    sendKeepAlive(true);
                });

                document.getElementById('session-timeout-logout').addEventListener('click', submitLogout);

                setLastActivityAt(lastActivityAt);
                scheduleTimers();
            })();
        </script>
    @endif
@endauth
