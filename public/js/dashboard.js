document.addEventListener('DOMContentLoaded', () => {
    // Reuse the active module's sidebar icon in the shared page hero. Pages without a
    // matching module stay intentionally icon-free instead of showing a generic symbol.
    const pageHeading = document.querySelector('.dashboard-page-heading');

    if (pageHeading) {
        const path = window.location.pathname;
        let sourceIcon = document.querySelector('.dashboard-nav-link.active .dashboard-nav-icon svg');

        if (!sourceIcon && path.startsWith('/account')) {
            sourceIcon = document.querySelector('.dashboard-settings-button svg');
        }

        if (!sourceIcon && path.startsWith('/school/periods')) {
            sourceIcon = document.querySelector('.dashboard-nav-link[href*="/school/timetable"] .dashboard-nav-icon svg');
        }

        const headingCopy = pageHeading.querySelector(':scope > div:first-child');

        if (sourceIcon && headingCopy) {
            const icon = document.createElement('span');
            icon.className = 'dashboard-page-heading-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.append(sourceIcon.cloneNode(true));
            headingCopy.prepend(icon);
            pageHeading.classList.add('has-page-icon');
        }
    }
    // Success messages ("Saved.", "Deleted." …) fade out after 2 seconds. Error alerts stay
    // on screen so they can be read and fixed.
    document.querySelectorAll('.alert[role="status"]').forEach((alert) => {
        setTimeout(() => {
            alert.classList.add('is-hiding');
            alert.addEventListener('transitionend', () => alert.remove(), { once: true });
            // Fallback when transitions are disabled (e.g. reduced motion).
            setTimeout(() => alert.remove(), 600);
        }, 2000);
    });

    // Sidebar groups collapse and expand. Groups the user opened are remembered in this
    // browser; the group of the open page is always expanded.
    const navGroups = document.querySelectorAll('[data-nav-group]');

    if (navGroups.length) {
        const storageKey = 'sidebar.openGroups';
        let openGroups = [];

        try {
            openGroups = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
        } catch {
            openGroups = [];
        }

        const setOpen = (group, open) => {
            group.classList.toggle('is-collapsed', !open);
            group.querySelector('.dashboard-nav-group-toggle')?.setAttribute('aria-expanded', String(open));
        };

        navGroups.forEach((group) => {
            if (!group.hasAttribute('data-nav-group-active') && openGroups.includes(group.dataset.navGroup)) {
                setOpen(group, true);
            }

            group.querySelector('.dashboard-nav-group-toggle')?.addEventListener('click', () => {
                const open = group.classList.contains('is-collapsed');
                setOpen(group, open);

                openGroups = openGroups.filter((id) => id !== group.dataset.navGroup);

                if (open) {
                    openGroups.push(group.dataset.navGroup);
                }

                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(openGroups));
                } catch {
                    // Storage can be blocked (private mode); the toggle still works for this page.
                }
            });
        });
    }

    // "Select all" toggles: each [data-check-all] controls every checkbox inside its
    // nearest [data-check-scope].
    document.querySelectorAll('[data-check-all]').forEach((toggle) => {
        const scope = toggle.closest('[data-check-scope]');

        if (!scope) {
            return;
        }

        const targets = () => Array.from(
            scope.querySelectorAll('input[type="checkbox"]:not([data-check-all])'),
        ).filter((checkbox) => !checkbox.disabled);

        const sync = () => {
            const boxes = targets();
            const checkedCount = boxes.filter((checkbox) => checkbox.checked).length;

            toggle.checked = boxes.length > 0 && checkedCount === boxes.length;
            toggle.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
        };

        toggle.addEventListener('change', () => {
            // Capture the requested state first: the re-dispatched child events call
            // sync(), which would otherwise flip the toggle mid-loop.
            const shouldCheck = toggle.checked;

            targets().forEach((checkbox) => {
                if (checkbox.checked !== shouldCheck) {
                    checkbox.checked = shouldCheck;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            sync();
        });

        scope.addEventListener('change', (event) => {
            if (event.target !== toggle) {
                sync();
            }
        });

        sync();
    });

    // User form: when the school changes, load that school's roles from the server
    // (platform roles stay). The server validates the final choice again.
    const schoolSelect = document.querySelector('[data-school-select]');
    const roleSelect = document.querySelector('[data-role-select]');
    const schoolRoles = roleSelect?.querySelector('[data-school-roles]');

    if (schoolSelect && roleSelect && schoolRoles && roleSelect.dataset.optionsUrl) {
        let requestId = 0;

        schoolSelect.addEventListener('change', async () => {
            const currentRequest = ++requestId;
            const selectedSchoolRole = schoolRoles.querySelector('option:checked');

            if (selectedSchoolRole) {
                roleSelect.value = '';
            }

            schoolRoles.replaceChildren();

            if (!schoolSelect.value) {
                return;
            }

            try {
                const url = new URL(roleSelect.dataset.optionsUrl, window.location.origin);
                url.searchParams.set('school_id', schoolSelect.value);

                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok || currentRequest !== requestId) {
                    return;
                }

                (await response.json()).forEach((role) => {
                    schoolRoles.append(new Option(role.name, role.id));
                });
            } catch {
                // Leave the list with platform roles only; saving still validates on the server.
            }
        });
    }

    // Super Admin school switcher (top bar): searches and pages schools on the server,
    // each result posts to the school's "enter" route.
    const switcher = document.querySelector('[data-school-switcher]');

    if (switcher) {
        const searchInput = switcher.querySelector('[data-school-search]');
        const results = switcher.querySelector('[data-school-results]');
        const moreButton = switcher.querySelector('[data-school-more]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        let requestId = 0;
        let nextUrl = null;
        let loaded = false;
        let searchTimer;

        const message = (text) => {
            const note = document.createElement('div');
            note.className = 'dashboard-school-note';
            note.textContent = text;
            results.replaceChildren(note);
        };

        const schoolItem = (school) => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = school.enter_url;

            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = csrfToken;

            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'dropdown-item dashboard-school-item';

            if (String(school.id) === switcher.dataset.activeSchool) {
                button.classList.add('active');
                button.setAttribute('aria-current', 'true');
            }

            const name = document.createElement('strong');
            name.textContent = school.name;

            const meta = document.createElement('small');
            meta.textContent = [school.code, school.status].filter(Boolean).join(' · ');

            button.append(name, meta);
            form.append(token, button);

            return form;
        };

        const load = async (url, append) => {
            const currentRequest = ++requestId;
            moreButton.hidden = true;

            if (!append) {
                message('Loading…');
            }

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (currentRequest !== requestId) {
                    return;
                }

                if (!response.ok) {
                    throw new Error();
                }

                const page = await response.json();

                if (!append) {
                    results.replaceChildren();
                }

                page.data.forEach((school) => results.append(schoolItem(school)));

                if (!results.children.length) {
                    message('No schools found');
                }

                nextUrl = page.next_page_url;
                moreButton.hidden = !nextUrl;
            } catch {
                if (currentRequest === requestId) {
                    message('Could not load schools');
                }
            }
        };

        const search = () => {
            const url = new URL(switcher.dataset.optionsUrl, window.location.origin);

            if (searchInput.value.trim()) {
                url.searchParams.set('search', searchInput.value.trim());
            }

            load(url, false);
        };

        switcher.addEventListener('shown.bs.dropdown', () => {
            if (!loaded) {
                loaded = true;
                search();
            }

            searchInput.focus();
        });

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(search, 300);
        });

        moreButton.addEventListener('click', () => {
            if (nextUrl) {
                load(nextUrl, true);
            }
        });
    }

    // Filters that apply as soon as they change: <select data-auto-submit>.
    document.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => field.form?.requestSubmit());
    });

    // Attendance: "mark everyone as" buttons set that status on every row of the form.
    document.querySelectorAll('[data-attendance-form]').forEach((form) => {
        form.querySelectorAll('[data-mark-all]').forEach((button) => {
            button.addEventListener('click', () => {
                form.querySelectorAll(`input[data-attendance-status="${button.dataset.markAll}"]:not(:disabled)`).forEach((radio) => {
                    radio.checked = true;
                });
            });
        });
    });

    // Menu order (Super Admin): ▲ ▼ move a group or a page within its own list; the hidden
    // inputs travel with the item, so the form posts the new order.
    document.querySelectorAll('[data-menu-order]').forEach((form) => {
        const refresh = () => {
            form.querySelectorAll('[data-sortable-list]').forEach((list) => {
                const items = Array.from(list.children).filter((child) => child.matches('[data-sortable-item]'));

                items.forEach((item, index) => {
                    const own = (selector) => Array.from(item.querySelectorAll(selector)).find((button) => button.closest('[data-sortable-item]') === item);
                    const up = own('[data-move="up"]');
                    const down = own('[data-move="down"]');

                    if (up) up.disabled = index === 0;
                    if (down) down.disabled = index === items.length - 1;
                });
            });
        };

        form.addEventListener('click', (event) => {
            const button = event.target.closest('[data-move]');

            if (!button || !form.contains(button)) {
                return;
            }

            const item = button.closest('[data-sortable-item]');
            const sibling = button.dataset.move === 'up' ? item.previousElementSibling : item.nextElementSibling;

            if (!sibling || !sibling.matches('[data-sortable-item]')) {
                return;
            }

            if (button.dataset.move === 'up') {
                sibling.before(item);
            } else {
                sibling.after(item);
            }

            item.classList.add('is-moved');
            setTimeout(() => item.classList.remove('is-moved'), 600);
            refresh();
            button.disabled ? button.closest('.menu-order-actions')?.querySelector('button:not(:disabled)')?.focus() : button.focus();
        });

        refresh();
    });

    // Notices: the bell lists the user's notices; clicking one (or any notice card with
    // data-notice-preview) opens the full notice in a modal and counts it as read.
    const noticeModalElement = document.querySelector('[data-notice-modal]');
    const bell = document.querySelector('[data-notice-bell]');
    const bellCount = bell?.querySelector('[data-bell-count]');

    const setUnread = (count) => {
        if (!bellCount) {
            return;
        }

        const unread = Math.max(0, count);
        bellCount.dataset.value = String(unread);
        bellCount.textContent = unread > 99 ? '99+' : String(unread);
        bellCount.hidden = unread === 0;
        bell.querySelector('button')?.setAttribute('aria-label', unread ? `Notices, ${unread} unread` : 'Notices');
    };

    if (bellCount) {
        bellCount.dataset.value = bellCount.hidden ? '0' : bellCount.textContent.replace('+', '');
    }

    const openNotice = async (trigger) => {
        if (!noticeModalElement || !window.bootstrap?.Modal) {
            return;
        }

        const field = (name) => noticeModalElement.querySelector(`[data-notice-modal-${name}]`);
        const modal = window.bootstrap.Modal.getOrCreateInstance(noticeModalElement);

        field('title').textContent = 'Loading…';
        field('meta').textContent = '';
        field('body').textContent = '';
        field('pin').hidden = true;
        modal.show();

        try {
            const response = await fetch(trigger.dataset.noticePreview, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error();
            }

            const notice = await response.json();

            field('title').textContent = notice.title;
            field('meta').textContent = notice.meta;
            // textContent keeps the text safe; CSS keeps its line breaks.
            field('body').textContent = notice.body || 'This notice has no further text.';
            field('pin').hidden = !notice.pinned;

            // Mark every copy of this notice on the page as read.
            document.querySelectorAll(`[data-notice-preview="${trigger.dataset.noticePreview}"]`).forEach((copy) => {
                if (copy.classList.contains('is-unread')) {
                    copy.classList.remove('is-unread');
                    copy.querySelector('.notice-new')?.remove();
                }
            });

            if (trigger.dataset.wasUnread === 'true' || trigger.classList.contains('was-unread')) {
                trigger.dataset.wasUnread = 'false';
                trigger.classList.remove('was-unread');
                setUnread(Number(bellCount?.dataset.value || 0) - 1);
            }
        } catch {
            field('title').textContent = 'Could not open this notice';
            field('body').textContent = 'Please try again, or open the notices page.';
        }
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-notice-preview]');

        if (!trigger || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        event.preventDefault();

        // A card that was still unread lowers the bell count once it is opened.
        if (trigger.classList.contains('is-unread') && !trigger.closest('[data-bell-list]')) {
            trigger.classList.add('was-unread');
        }

        if (trigger.closest('[data-bell-list]') && window.bootstrap?.Dropdown) {
            window.bootstrap.Dropdown.getInstance(bell.querySelector('[data-bs-toggle="dropdown"]'))?.hide();
        }

        openNotice(trigger);
    });

    if (bell) {
        const list = bell.querySelector('[data-bell-list]');
        const moreButton = bell.querySelector('[data-bell-more]');
        let nextUrl = null;
        let loaded = false;

        const note = (text) => {
            const element = document.createElement('div');
            element.className = 'dashboard-school-note';
            element.textContent = text;
            list.replaceChildren(element);
        };

        const item = (notice) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `dropdown-item dashboard-bell-item${notice.read ? '' : ' is-unread'}`;
            button.dataset.noticePreview = notice.preview_url;
            button.dataset.wasUnread = String(!notice.read);

            const head = document.createElement('span');
            head.className = 'dashboard-bell-item-head';

            if (notice.pinned) {
                const pin = document.createElement('span');
                pin.className = 'notice-pin';
                pin.textContent = '📌 Pinned';
                head.append(pin);
            }

            const date = document.createElement('small');
            date.textContent = notice.date;
            head.append(date);

            const title = document.createElement('strong');
            title.textContent = notice.title;

            button.append(head, title);

            if (notice.excerpt) {
                const excerpt = document.createElement('small');
                excerpt.className = 'dashboard-bell-excerpt';
                excerpt.textContent = notice.excerpt;
                button.append(excerpt);
            }

            return button;
        };

        const load = async (url, append) => {
            moreButton.hidden = true;

            if (!append) {
                note('Loading…');
            }

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error();
                }

                const page = await response.json();

                if (!append) {
                    list.replaceChildren();
                }

                page.data.forEach((notice) => list.append(item(notice)));

                if (!list.children.length) {
                    note('No notices for you right now.');
                }

                setUnread(page.unread);
                nextUrl = page.next_page_url;
                moreButton.hidden = !nextUrl;
            } catch {
                note('Could not load notices.');
            }
        };

        bell.addEventListener('show.bs.dropdown', () => {
            // Refresh every time it opens so new notices appear without reloading the page.
            load(bell.dataset.feedUrl, false);
            loaded = true;
        });

        moreButton.addEventListener('click', () => {
            if (nextUrl && loaded) {
                load(nextUrl, true);
            }
        });
    }

    // Notice form: show role / class choices only for "Selected", and the time only for "Schedule".
    document.querySelectorAll('[data-notice-form]').forEach((form) => {
        const targets = form.querySelector('[data-audience-targets]');
        const publishAt = form.querySelector('[data-publish-at]');

        const sync = () => {
            const audience = form.querySelector('[data-audience-choice]:checked')?.value;
            const mode = form.querySelector('[data-publish-choice]:checked')?.value;

            if (targets) targets.hidden = audience !== 'selected';
            if (publishAt) publishAt.hidden = mode !== 'schedule';
        };

        form.querySelectorAll('[data-audience-choice], [data-publish-choice]').forEach((input) => {
            input.addEventListener('change', sync);
        });

        sync();
    });

    // Ask before destructive actions: <form data-confirm="Delete this class?">.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Colour override selects by their value so Allow/Deny stand out.
    document.querySelectorAll('.access-override-select').forEach((select) => {
        select.addEventListener('change', () => {
            select.classList.remove('is-set', 'is-allow', 'is-deny');

            if (select.value !== '') {
                select.classList.add('is-set', `is-${select.value}`);
            }
        });
    });
});
