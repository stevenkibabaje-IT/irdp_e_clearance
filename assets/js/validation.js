// Browser checks give immediate feedback; PHP still validates submitted values.
(() => {
    const namePattern = /^[\p{L}][\p{L}\p{M} .'’\-]*$/u;
    document.querySelectorAll('form').forEach(form => {
        const validate = input => {
            if (!input.setCustomValidity || input.type === 'hidden') return;
            // Required fields depend on the account role and scheduling mode.
            const accountRole = form.elements.namedItem('role_id')?.selectedOptions[0]?.textContent.trim();
            if (accountRole && ['programme', 'academic_year'].includes(input.name)) input.required = accountRole === 'STUDENT';
            if (accountRole && input.name === 'department_id') input.required = accountRole === 'STUDENT'
                || (['OFFICER', 'SUPERVISOR'].includes(accountRole) && form.elements.namedItem('office_id')?.value === form.dataset.departmentOfficeId);
            if (accountRole && input.name === 'office_id') input.required = accountRole === 'OFFICER';
            if (form.elements.namedItem('new_cycle')) {
                if (input.name === 'cycle_id') input.required = !form.elements.namedItem('new_cycle').value.trim();
                if (['opens_at', 'closes_at'].includes(input.name)) input.required = form.elements.namedItem('mode')?.value === 'SCHEDULED';
            }
            if (form.elements.namedItem('type')?.value && ['from', 'to'].includes(input.name)) {
                input.required = form.elements.namedItem('type').value === 'custom';
            }
            input.setCustomValidity('');
            const value = input.value;
            if (typeof value !== 'string') return;
            if (input.required && input.type !== 'file' && !value.trim()) {
                input.setCustomValidity('This field is required.');
            } else if (input.dataset.personName && value && !namePattern.test(value.trim())) {
                input.setCustomValidity('Please enter a valid full name.');
            } else if (input.dataset.clearanceAmount && value && !/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/.test(value)) {
                input.setCustomValidity('Use a non-negative amount up to 999999999999.99, with at most two decimal places.');
            } else if (input.name === 'to' && form.elements.namedItem('type')?.value === 'custom'
                && value && form.elements.namedItem('from')?.value && value < form.elements.namedItem('from').value) {
                input.setCustomValidity('The end date must be on or after the start date.');
            } else if (input.name === 'new_cycle' && value.trim()) {
                const cycle = /^(20\d{2})\/(20\d{2})$/.exec(value.trim());
                if (!cycle || Number(cycle[2]) !== Number(cycle[1]) + 1) input.setCustomValidity('Use consecutive academic years, for example 2026/2027.');
            } else if (input.name === 'closes_at' && value && form.elements.namedItem('opens_at')?.value && value <= form.elements.namedItem('opens_at').value) {
                input.setCustomValidity('Closing date must follow opening date.');
            } else if (input.name === 'username' && form.elements.namedItem('role_id')) {
                const role = form.elements.namedItem('role_id');
                if (role.selectedOptions[0]?.textContent.trim() === 'STUDENT' && !/^IRDP\/[A-Z][A-Z0-9]{1,19}\/[A-Z]{2}[0-9]{2}\/[0-9]{4,10}$/.test(value.trim())) input.setCustomValidity('Registration number is invalid.');
            } else if (input.type === 'file' && input.accept) {
                const allowed = input.accept.split(',').map(extension => extension.trim().toLowerCase());
                const extensions = allowed.filter(token => token.startsWith('.'));
                if (input.name === 'evidence[]' && input.files.length > 5) input.setCustomValidity('Upload at most five evidence files.');
                for (const file of input.files) {
                    const extension = '.' + file.name.split('.').pop().toLowerCase();
                    const permitted = extensions.length ? extensions.includes(extension) : allowed.some(token =>
                        token.endsWith('/*') ? file.type.startsWith(token.slice(0, -1)) : file.type === token);
                    if (!permitted) input.setCustomValidity('Please select a permitted file type.');
                    if ((input.name === 'picture' || input.name === 'evidence[]' || input.name === 'file') && (file.size === 0 || file.size > 5242880)) input.setCustomValidity('Choose a nonempty file up to 5 MB.');
                }
            } else if (input.dataset.passwordMin && value) {
                // Count Unicode code points rather than UTF-16 units for password length.
                const length = Array.from(value).length;
                if (length < Number(input.dataset.passwordMin)) input.setCustomValidity(`Password must contain at least ${input.dataset.passwordMin} characters.`);
                else if (length > Number(input.dataset.passwordMax)) input.setCustomValidity(`Password must contain at most ${input.dataset.passwordMax} characters.`);
                else if (input.name === 'confirm_password' && value !== form.elements.namedItem('password')?.value) input.setCustomValidity('New password and confirmation password do not match.');
            }
        };
        Array.from(form.elements).forEach(validate);
        form.addEventListener('input', () => Array.from(form.elements).forEach(validate));
        form.addEventListener('change', () => Array.from(form.elements).forEach(validate));
        form.addEventListener('submit', event => {
            Array.from(form.elements).forEach(validate);
            if (!form.reportValidity()) event.preventDefault();
        });
    });
})();
