document.addEventListener('DOMContentLoaded', function () {
    const dashboardSidebars = document.querySelectorAll('.dashboard-page > .sidebar');
    if (dashboardSidebars.length) {
        document.body.classList.add('has-dashboard-menu');

        dashboardSidebars.forEach(function (sidebar, index) {
            const nav = sidebar.querySelector('nav');
            if (!nav) {
                return;
            }

            const navId = 'dashboard-navigation-' + index;
            nav.id = navId;

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'sidebar-menu-toggle';
            toggle.setAttribute('aria-controls', navId);
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open dashboard menu');

            const icon = document.createElement('span');
            icon.className = 'sidebar-menu-icon';
            icon.setAttribute('aria-hidden', 'true');
            for (let line = 0; line < 3; line++) {
                icon.appendChild(document.createElement('span'));
            }

            const label = document.createElement('span');
            label.textContent = 'Menu';
            toggle.append(icon, label);
            sidebar.insertBefore(toggle, nav);

            function closeMenu() {
                sidebar.classList.remove('is-menu-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open dashboard menu');
            }

            toggle.addEventListener('click', function () {
                const isOpen = sidebar.classList.toggle('is-menu-open');
                toggle.setAttribute('aria-expanded', String(isOpen));
                toggle.setAttribute('aria-label', isOpen ? 'Close dashboard menu' : 'Open dashboard menu');
            });

            nav.addEventListener('click', function (event) {
                if (event.target.closest('a')) {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && sidebar.classList.contains('is-menu-open')) {
                    closeMenu();
                    toggle.focus();
                }
            });

            document.addEventListener('click', function (event) {
                if (!sidebar.contains(event.target)) {
                    closeMenu();
                }
            });

            window.matchMedia('(min-width: 701px)').addEventListener('change', closeMenu);
        });
    }

    const loginDialog = document.querySelector('#login-dialog');
    const loginTriggers = document.querySelectorAll('[data-login-trigger]');
    const loginClose = document.querySelector('[data-login-close]');
    const forgotDialog = document.querySelector('#forgot-dialog');
    const forgotTriggers = document.querySelectorAll('[data-forgot-trigger]');
    const forgotCloseButtons = document.querySelectorAll('[data-forgot-close]');
    const registerDialog = document.querySelector('#register-dialog');
    const registerTriggers = document.querySelectorAll('[data-register-trigger]');
    const registerClose = document.querySelector('[data-register-close]');

    if (loginDialog && typeof loginDialog.showModal === 'function') {
        loginTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                if (registerDialog && registerDialog.open) {
                    registerDialog.close();
                }
                loginDialog.showModal();
            });
        });

        loginClose.addEventListener('click', function () {
            loginDialog.close();
        });

        loginDialog.addEventListener('click', function (event) {
            if (event.target === loginDialog) {
                loginDialog.close();
            }
        });

        loginDialog.addEventListener('close', function () {
            if (loginTriggers.length) {
                loginTriggers[0].focus();
            }
        });

        if (new URLSearchParams(window.location.search).has('login')) {
            loginDialog.showModal();
            document.querySelector('#home-login-email').focus();
        }
    }

    if (forgotDialog && typeof forgotDialog.showModal === 'function') {
        forgotTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                forgotDialog.showModal();
            });
        });

        forgotCloseButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                forgotDialog.close();
            });
        });

        forgotDialog.addEventListener('click', function (event) {
            if (event.target === forgotDialog) {
                forgotDialog.close();
            }
        });

        forgotDialog.addEventListener('close', function () {
            if (forgotTriggers.length) {
                forgotTriggers[0].focus();
            }
        });

        if (new URLSearchParams(window.location.search).has('forgot')) {
            if (loginDialog && !loginDialog.open) {
                loginDialog.showModal();
            }
            forgotDialog.showModal();
            document.querySelector('#forgot-recovery-code').focus();
        }
    }

    if (registerDialog && typeof registerDialog.showModal === 'function') {
        registerTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                if (loginDialog && loginDialog.open) {
                    loginDialog.close();
                }
                registerDialog.showModal();
            });
        });

        registerClose.addEventListener('click', function () {
            registerDialog.close();
        });

        registerDialog.addEventListener('click', function (event) {
            if (event.target === registerDialog) {
                registerDialog.close();
            }
        });

        registerDialog.addEventListener('close', function () {
            if (registerTriggers.length) {
                registerTriggers[0].focus();
            }
        });

        if (new URLSearchParams(window.location.search).has('register')) {
            registerDialog.showModal();
            document.querySelector('#home-register-name').focus();
        }
    }

    const logoutDialog = document.querySelector('#logout-dialog');
    const logoutTrigger = document.querySelector('[data-logout-trigger]');
    const logoutCancel = document.querySelector('[data-logout-cancel]');

    if (logoutDialog && logoutTrigger && logoutCancel && typeof logoutDialog.showModal === 'function') {
        logoutTrigger.addEventListener('click', function (event) {
            event.preventDefault();
            logoutDialog.showModal();
            logoutCancel.focus();
        });

        logoutCancel.addEventListener('click', function () {
            logoutDialog.close();
        });

        logoutDialog.addEventListener('click', function (event) {
            if (event.target === logoutDialog) {
                logoutDialog.close();
            }
        });

        logoutDialog.addEventListener('close', function () {
            logoutTrigger.focus();
        });
    }

    window.addEventListener('pageshow', function (event) {
        if (event.persisted && document.querySelector('.dashboard-page')) {
            window.location.reload();
        }
    });

    const noticeDialogs = Array.from(document.querySelectorAll('[data-notice-dialog]'));
    let noticeIndex = 0;

    function showNextNotice() {
        if (noticeIndex >= noticeDialogs.length) {
            return;
        }

        const noticeDialog = noticeDialogs[noticeIndex++];
        if (typeof noticeDialog.showModal !== 'function') {
            noticeDialog.remove();
            showNextNotice();
            return;
        }

        noticeDialog.showModal();
        window.setTimeout(function () {
            noticeDialog.classList.add('is-leaving');
            window.setTimeout(function () {
                noticeDialog.close();
                showNextNotice();
            }, 180);
        }, 800);
    }

    showNextNotice();

    const liveDashboard = document.querySelector('[data-live-dashboard]');
    if (liveDashboard) {
        let liveDashboardRequestPending = false;
        window.setInterval(function () {
            if (liveDashboardRequestPending || document.visibilityState !== 'visible') {
                return;
            }

            liveDashboardRequestPending = true;
            fetch(liveDashboard.dataset.liveDashboard, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Dashboard refresh failed.');
                    }
                    return response.json();
                })
                .then(function (data) {
                    Object.entries(data.metrics || {}).forEach(function ([key, value]) {
                        liveDashboard.querySelectorAll('[data-live-value="' + key + '"]').forEach(function (metric) {
                            metric.textContent = value;
                        });
                    });

                    Object.entries(data.lists || {}).forEach(function ([key, entries]) {
                        const list = liveDashboard.querySelector('[data-live-list="' + key + '"]');
                        if (!list) {
                            return;
                        }

                        list.replaceChildren();
                        if (!entries.length) {
                            const emptyItem = document.createElement('li');
                            emptyItem.textContent = 'No recent quiz activity.';
                            list.appendChild(emptyItem);
                            return;
                        }
                        entries.forEach(function (entry) {
                            const item = document.createElement('li');
                            const label = document.createElement('span');
                            const detail = document.createElement('small');
                            label.textContent = entry.label;
                            detail.textContent = entry.detail;
                            item.append(label, detail);
                            list.appendChild(item);
                        });
                    });
                })
                .catch(function () {})
                .finally(function () {
                    liveDashboardRequestPending = false;
                });
        }, 15000);
    }

    const forms = document.querySelectorAll('form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type="submit"]');
            if (button && !button.dataset.loading) {
                button.dataset.loading = 'true';
                button.disabled = true;
                const originalText = button.textContent;
                button.textContent = 'Please wait...';
                button.setAttribute('data-original-text', originalText);
            }
        });
    });

    const liveProgress = document.querySelector('[data-live-value="progress"]');
    if (liveProgress) {
        let dashboardRequestPending = false;

        function updateDashboardList(selector, entries, emptyMessage, primaryKey, secondaryKey) {
            const list = document.querySelector(selector);
            if (!list) {
                return;
            }

            list.replaceChildren();
            if (!entries.length) {
                const emptyItem = document.createElement('li');
                emptyItem.textContent = emptyMessage;
                list.appendChild(emptyItem);
                return;
            }

            entries.forEach(function (entry) {
                const item = document.createElement('li');
                const primary = document.createElement('span');
                const secondary = document.createElement('small');
                primary.textContent = entry[primaryKey] || '';
                secondary.textContent = entry[secondaryKey] || '';
                item.append(primary, secondary);
                list.appendChild(item);
            });
        }

        function refreshStudentDashboard() {
            if (dashboardRequestPending || document.visibilityState !== 'visible') {
                return;
            }

            dashboardRequestPending = true;
            fetch('dashboard_data.php', { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Dashboard refresh failed.');
                    }
                    return response.json();
                })
                .then(function (data) {
                    document.querySelector('[data-live-value="progress"]').textContent = Number(data.progress).toFixed(1) + '%';
                    document.querySelector('[data-live-value="completed"]').textContent = data.completed;
                    document.querySelector('[data-live-value="badges"]').textContent = data.badges_count;
                    document.querySelector('[data-live-value="score"]').textContent = Number(data.average_score).toFixed(2) + '%';
                    updateDashboardList('[data-live-list="lessons"]', data.lessons, 'No lessons are available yet.', 'title', 'topic');
                    updateDashboardList('[data-live-list="badges"]', data.badges, 'Complete lessons and quizzes to earn badges.', 'name', 'description');
                    updateDashboardList('[data-live-list="scores"]', data.recent_scores, 'No quizzes taken yet.', 'quiz_title', 'detail');
                })
                .catch(function () {})
                .finally(function () {
                    dashboardRequestPending = false;
                });
        }

        window.setInterval(refreshStudentDashboard, 15000);
    }
});
