(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const toast = (icon, title) => {
        if (window.Swal) {
            Swal.fire({toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2800, timerProgressBar: true});
        }
    };

    if (window.taskflowFlash) toast(window.taskflowFlash.type === 'success' ? 'success' : 'error', window.taskflowFlash.message);

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmed === 'yes') return;
        event.preventDefault();
        const result = await Swal.fire({
            title: form.dataset.confirmTitle || 'Konfirmasi tindakan',
            text: form.dataset.confirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            reverseButtons: true
        });
        if (result.isConfirmed) {
            form.dataset.confirmed = 'yes';
            form.requestSubmit();
        }
    });

    const checklistRoot = document.querySelector('[data-checklist]');
    const detail = document.querySelector('.detail-layout[data-task-id]');
    if (checklistRoot && detail && detail.dataset.archived !== '1') {
        const taskId = detail.dataset.taskId;
        const endpoint = detail.dataset.apiUrl;
        const errorBox = document.querySelector('[data-checklist-error]');
        const setBusy = (busy) => {
            checklistRoot.closest('.checklist-panel').classList.toggle('is-loading', busy);
        };
        const send = async (data) => {
            setBusy(true);
            errorBox.textContent = '';
            try {
                const body = new URLSearchParams({...data, task_id: taskId});
                const response = await fetch(endpoint, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf}, body});
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'Checklist gagal diperbarui.');
                render(result);
                return true;
            } catch (error) {
                errorBox.textContent = error.message || 'Koneksi gagal. Silakan coba kembali.';
                toast('error', errorBox.textContent);
                return false;
            } finally {
                setBusy(false);
            }
        };
        const render = (result) => {
            const state = result.state;
            const list = document.querySelector('[data-checklist]');
            list.replaceChildren();
            result.items.forEach((item, index) => {
                const row = document.createElement('div');
                row.className = `checklist-item${Number(item.is_completed) ? ' is-completed' : ''}`;
                row.dataset.itemId = item.id;
                row.dataset.itemTitle = item.title;
                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'check-toggle';
                toggle.dataset.itemAction = 'toggle';
                toggle.setAttribute('aria-label', 'Tandai checklist');
                const checkIcon = document.createElement('i');
                checkIcon.className = `bi ${Number(item.is_completed) ? 'bi-check-circle-fill' : 'bi-circle'}`;
                toggle.append(checkIcon);
                const title = document.createElement('span');
                title.className = 'check-title';
                title.textContent = item.title;
                const actions = document.createElement('div');
                actions.className = 'check-actions';
                [['up','bi-arrow-up',index === 0],['down','bi-arrow-down',index === result.items.length - 1],['edit','bi-pencil',false],['delete','bi-trash3',false]].forEach(([action, icon, disabled]) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `icon-button${action === 'delete' ? ' danger-hover' : ''}`;
                    button.dataset.itemAction = action;
                    button.disabled = disabled;
                    button.setAttribute('aria-label', action);
                    const i = document.createElement('i');
                    i.className = `bi ${icon}`;
                    button.append(i);
                    actions.append(button);
                });
                row.append(toggle, title, actions);
                list.append(row);
            });
            let empty = document.querySelector('[data-checklist-empty]');
            if (!result.items.length && !empty) {
                empty = document.createElement('p');
                empty.className = 'checklist-empty';
                empty.dataset.checklistEmpty = '';
                empty.textContent = 'Belum ada checklist. Tambahkan langkah kerja di bawah.';
                list.after(empty);
            } else if (result.items.length && empty) empty.remove();
            document.querySelector('[data-progress-percent]').textContent = `${state.progress}%`;
            document.querySelector('[data-progress-bar]').style.width = `${state.progress}%`;
            document.querySelector('[data-progress-count]').textContent = `${state.completed} / ${state.total} selesai`;
            const status = document.querySelector('[data-task-status]');
            status.textContent = state.status;
            status.className = `status-pill ${state.status === 'Completed' ? 'status-completed' : state.status === 'In Progress' ? 'status-progress' : 'status-pending'}`;
        };

        document.querySelector('[data-checklist-add]')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const input = form.elements.title;
            if (await send({action: 'add', title: input.value.trim()})) {
                input.value = '';
                toast('success', 'Checklist ditambahkan.');
            }
        });

        checklistRoot.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-item-action]');
            if (!button || button.disabled) return;
            const row = button.closest('[data-item-id]');
            const itemId = row.dataset.itemId;
            const action = button.dataset.itemAction;
            if (action === 'toggle') {
                if (await send({action: 'toggle', item_id: itemId})) toast('success', 'Progress tugas diperbarui.');
            } else if (action === 'delete') {
                const answer = await Swal.fire({title: 'Hapus checklist?', text: 'Item ini akan dihapus dari tugas.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal', reverseButtons: true});
                if (answer.isConfirmed && await send({action: 'delete', item_id: itemId})) toast('success', 'Checklist dihapus.');
            } else if (action === 'edit') {
                const answer = await Swal.fire({title: 'Edit checklist', input: 'text', inputValue: row.dataset.itemTitle, inputAttributes: {maxlength: 240}, showCancelButton: true, confirmButtonText: 'Simpan', cancelButtonText: 'Batal', inputValidator: value => !value?.trim() ? 'Nama checklist wajib diisi.' : undefined});
                if (answer.isConfirmed && await send({action: 'edit', item_id: itemId, title: answer.value.trim()})) toast('success', 'Checklist diperbarui.');
            } else if (action === 'up' || action === 'down') {
                const rows = [...checklistRoot.querySelectorAll('[data-item-id]')];
                const index = rows.indexOf(row);
                const other = rows[index + (action === 'up' ? -1 : 1)];
                if (other) {
                    const ids = rows.map(item => item.dataset.itemId);
                    [ids[index], ids[index + (action === 'up' ? -1 : 1)]] = [ids[index + (action === 'up' ? -1 : 1)], ids[index]];
                    if (await send({action: 'reorder', item_ids: ids})) toast('success', 'Urutan checklist diperbarui.');
                }
            }
        });
    }

    document.querySelector('[data-edit-note]')?.closest('.notes-panel')?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-edit-note]');
        if (!button) return;
        const answer = await Swal.fire({title: 'Edit catatan', input: 'textarea', inputValue: button.dataset.noteContent, inputAttributes: {maxlength: 4000, rows: 5}, showCancelButton: true, confirmButtonText: 'Simpan', cancelButtonText: 'Batal', inputValidator: value => !value?.trim() ? 'Catatan wajib diisi.' : undefined});
        if (!answer.isConfirmed) return;
        const form = document.createElement('form');
        form.method = 'post';
        form.action = `index.php?page=task-detail&id=${encodeURIComponent(button.dataset.taskId)}&section=notes`;
        const fields = {csrf_token: csrf, action: 'note-update', task_id: button.dataset.taskId, note_id: button.dataset.noteId, content: answer.value.trim(), return_page: 'task-detail'};
        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.append(input);
        });
        document.body.append(form);
        form.submit();
    });

    document.querySelector('.comments-panel')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-edit-comment]');
        if (!button) return;
        Swal.fire({
            title: 'Edit komentar',
            input: 'textarea',
            inputValue: button.dataset.content,
            inputAttributes: {maxlength: 4000, rows: 5},
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            inputValidator: value => !value?.trim() ? 'Komentar wajib diisi.' : undefined
        }).then(answer => {
            if (!answer.isConfirmed) return;
            const form = document.createElement('form');
            form.method = 'post';
            form.action = `index.php?page=task-detail&id=${encodeURIComponent(document.querySelector('[data-task-id]').dataset.taskId)}`;
            const fields = {csrf_token: csrf, action: 'task-comment-update', task_id: document.querySelector('[data-task-id]').dataset.taskId, comment_id: button.dataset.commentId, content: answer.value.trim(), return_page: 'task-detail'};
            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.append(input);
            });
            document.body.append(form);
            form.submit();
        });
    });

    const fields = document.querySelector('#checklistFields');
    document.querySelector('#addChecklistField')?.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'checklist-input-row';
        row.innerHTML = '<i class="bi bi-grip-vertical"></i><input name="checklist[]" maxlength="240" placeholder="Langkah pekerjaan"><button class="icon-button remove-checklist-field" type="button" aria-label="Hapus item"><i class="bi bi-x-lg"></i></button>';
        fields.append(row);
        row.querySelector('input').focus();
    });
    fields?.addEventListener('click', (event) => {
        if (event.target.closest('.remove-checklist-field')) {
            const row = event.target.closest('.checklist-input-row');
            if (fields.children.length > 1) row.remove();
            else row.querySelector('input').value = '';
        }
    });

    const projectModal = document.querySelector('[data-project-modal]');
    const openProjectModal = () => {
        if (!projectModal) return;
        projectModal.hidden = false;
        document.body.classList.add('modal-open');
        projectModal.querySelector('input[name="name"]')?.focus();
    };
    const closeProjectModal = () => {
        if (!projectModal) return;
        projectModal.hidden = true;
        document.body.classList.remove('modal-open');
    };
    document.querySelector('[data-project-create]')?.addEventListener('click', openProjectModal);
    document.querySelector('[data-project-edit]')?.addEventListener('click', openProjectModal);
    document.querySelectorAll('[data-project-modal-close]').forEach((button) => button.addEventListener('click', closeProjectModal));
    projectModal?.addEventListener('click', (event) => {
        if (event.target === projectModal) closeProjectModal();
    });
})();
