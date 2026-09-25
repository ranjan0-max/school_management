document.addEventListener('DOMContentLoaded', () => {
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
