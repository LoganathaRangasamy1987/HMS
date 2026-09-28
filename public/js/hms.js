document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        });
    });

    document.querySelectorAll('[data-membership-row]').forEach((row) => {
        const checkbox = row.querySelector('[data-membership-toggle]');
        const synchronize = () => {
            row.classList.toggle('is-selected', checkbox.checked);
            row.querySelectorAll('[data-membership-control]').forEach((control) => {
                control.disabled = !checkbox.checked;
                control.required = checkbox.checked && control.name.endsWith('[role_id]');
            });
        };
        checkbox.addEventListener('change', synchronize);
        synchronize();
    });

    document.querySelectorAll('[data-unknown-date]').forEach((checkbox) => {
        const dateInput = document.getElementById(checkbox.dataset.unknownDate);
        if (!dateInput) return;
        const synchronize = () => {
            dateInput.disabled = checkbox.checked;
            dateInput.required = !checkbox.checked;
        };
        checkbox.addEventListener('change', synchronize);
        synchronize();
    });

    document.querySelectorAll('[data-appointment-booking]').forEach((form) => {
        const doctor = form.querySelector('[data-appointment-doctor]');
        const date = form.querySelector('[data-appointment-date]');
        const time = form.querySelector('[data-appointment-time]');
        const status = form.querySelector('[data-appointment-availability-status]');
        if (!doctor || !date || !time || !status) return;

        let availableDates = [];
        let requestController;
        const replaceOptions = (select, placeholder, options = []) => {
            select.replaceChildren(new Option(placeholder, ''));
            options.forEach((option) => select.add(new Option(option.label, option.value)));
        };
        const loadTimes = () => {
            const selected = availableDates.find((entry) => entry.date === date.value);
            replaceOptions(time, selected ? 'Select a time' : 'Select a date first', (selected?.slots || []).map((slot) => ({
                value: slot.time,
                label: `${slot.time}–${slot.ends_at} (${slot.remaining} ${slot.remaining === 1 ? 'place' : 'places'} left)`,
            })));
            time.disabled = !selected;
            const oldTime = time.dataset.oldValue;
            if (oldTime && selected?.slots.some((slot) => slot.time === oldTime)) {
                time.value = oldTime;
                delete time.dataset.oldValue;
            }
        };
        const loadAvailability = async () => {
            requestController?.abort();
            availableDates = [];
            replaceOptions(date, doctor.value ? 'Loading available dates…' : 'Select doctor first');
            replaceOptions(time, 'Select a date first');
            date.disabled = true;
            time.disabled = true;
            if (!doctor.value) {
                status.textContent = 'Select a doctor to load the next 30 days of configured availability.';
                return;
            }
            requestController = new AbortController();
            status.textContent = 'Loading available dates and time slots…';
            try {
                const url = new URL(form.dataset.availabilityUrl, window.location.origin);
                url.searchParams.set('doctor_profile_id', doctor.value);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: requestController.signal });
                if (!response.ok) throw new Error('Availability could not be loaded.');
                const payload = await response.json();
                availableDates = payload.data.dates;
                replaceOptions(date, availableDates.length ? 'Select an available date' : 'No available dates', availableDates.map((entry) => ({ value: entry.date, label: entry.label })));
                date.disabled = availableDates.length === 0;
                status.textContent = availableDates.length
                    ? `Showing ${availableDates.length} available ${availableDates.length === 1 ? 'date' : 'dates'} in ${payload.data.timezone}.`
                    : 'This doctor has no open appointment slots in the next 30 days.';
                const oldDate = date.dataset.oldValue;
                if (oldDate && availableDates.some((entry) => entry.date === oldDate)) {
                    date.value = oldDate;
                    delete date.dataset.oldValue;
                    loadTimes();
                }
            } catch (error) {
                if (error.name === 'AbortError') return;
                replaceOptions(date, 'Unable to load dates');
                status.textContent = 'Availability could not be loaded. Refresh the page and try again.';
            }
        };

        doctor.addEventListener('change', loadAvailability);
        date.addEventListener('change', loadTimes);
        if (doctor.value) loadAvailability();
    });

    const errors = document.querySelector('[data-validation-summary]');
    if (errors) errors.focus({ preventScroll: false });
});
