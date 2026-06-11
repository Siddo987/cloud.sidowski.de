// /js/main.js
document.addEventListener('DOMContentLoaded', () => {
    // --- Theme Toggling ---
    const themeButtons = document.querySelectorAll('.theme-switch-button');
    const htmlElement = document.documentElement;
    let currentPreference = localStorage.getItem('themePreference') || 'system';
    function applyTheme(preference) {
        let themeToSet;
        if (preference === 'system') {
            const prefersDarkOS = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            themeToSet = prefersDarkOS ? 'dark' : 'light';
            htmlElement.removeAttribute('data-theme');
            console.log("Theme: System -> Using OS:", themeToSet);
        } else {
            themeToSet = preference;
            htmlElement.setAttribute('data-theme', themeToSet);
            localStorage.setItem('themePreference', preference);
            console.log("Theme: Applying explicit:", themeToSet);
        }
        themeButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.theme === preference);
        });
    }
    themeButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const selectedPreference = button.dataset.theme;
            currentPreference = selectedPreference;
            applyTheme(selectedPreference);
        });
    });
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    function handleSystemThemeChange() {
        if (currentPreference === 'system') {
            console.log("Theme: OS changed, re-applying system theme...");
            applyTheme('system');
        }
    }
    try {
        mediaQuery.addEventListener('change', handleSystemThemeChange);
    } catch (e) {
        /* Fallback */
    }
    applyTheme(currentPreference);
    // --- Dropdown Menu Logic ---
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            const parentNavItem = this.closest('.nav-item');
            const menu = parentNavItem?.querySelector('.dropdown-menu');
            if (!menu || !parentNavItem) {
                console.error('Dropdown menu structure incorrect.');
                return;
            }
            closeAllDropdowns(parentNavItem);
            const isOpen = menu.classList.toggle('active');
            this.setAttribute('aria-expanded', isOpen);
        });
    });
    function closeAllDropdowns(exceptThisNavItem = null) {
        document.querySelectorAll('.dropdown-menu.active').forEach(openMenu => {
            const parent = openMenu.closest('.nav-item');
            if (parent !== exceptThisNavItem) {
                openMenu.classList.remove('active');
                const toggle = parent?.querySelector('.dropdown-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }
    document.addEventListener('click', function(event) {
        const clickedNavItem = event.target.closest('.nav-item');
        const isClickInsideDropdown = clickedNavItem?.querySelector('.dropdown-menu') !== null;
        if (!isClickInsideDropdown) {
            closeAllDropdowns();
        }
    });
    // --- Upload Form Logic ---
    const uploadForm = document.getElementById('upload-form');
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const fileListContainer = document.getElementById('file-list');
    const submitButton = uploadForm?.querySelector('button[type="submit"]');
    const noFilesText = fileListContainer?.dataset.noFilesText || 'Keine Dateien ausgewählt.';
    let selectedFiles = [];
    function updateFileList() {
        if (!fileListContainer || !submitButton) return;
        fileListContainer.innerHTML = '';
        if (selectedFiles.length === 0) {
            fileListContainer.innerHTML = `
${noFilesText}

`;
            submitButton.disabled = true;
        } else {
            const ul = document.createElement('ul');
            selectedFiles.forEach((file, index) => {
                const li = document.createElement('li');
                const textSpan = document.createElement('span');
                textSpan.className = 'file-list-text';
                textSpan.textContent = `${file.name} (${formatBytes(file.size)})`;
                li.appendChild(textSpan);
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-file-btn';
                removeBtn.innerHTML = '×';
                removeBtn.title = 'Datei entfernen';
                removeBtn.dataset.index = index;
                removeBtn.addEventListener('click', removeFile);
                li.appendChild(removeBtn);
                ul.appendChild(li);
            });
            fileListContainer.appendChild(ul);
            submitButton.disabled = false;
        }
    }
    function addFiles(files) {
        for (const file of files) {
            const isDuplicate = selectedFiles.some( existingFile => existingFile.name === file.name && existingFile.size === file.size );
            if (!isDuplicate) {
                selectedFiles.push(file);
            }
        }
        updateFileList();
    }
    function removeFile(event) {
        const indexToRemove = parseInt(event.target.dataset.index, 10);
        if (!isNaN(indexToRemove) && indexToRemove >= 0 && indexToRemove < selectedFiles.length) {
            selectedFiles.splice(indexToRemove, 1);
            updateFileList();
        }
    }
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    if (uploadForm && dropZone && fileInput && fileListContainer && submitButton) {
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
        dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('dragover'); });
        dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); if (e.dataTransfer.files.length) { addFiles(e.dataTransfer.files); try { const dt = new DataTransfer(); selectedFiles.forEach(f => dt.items.add(f)); fileInput.files = dt.files; } catch (err) { console.error("FileList update failed:", err); } } });
        fileInput.addEventListener('change', () => { selectedFiles = fileInput.files.length ? Array.from(fileInput.files) : []; updateFileList(); });
        updateFileList();
    }
    // --- Alert Auto-Fade Out ---
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (!alert.classList.contains('alert-error')) {
            setTimeout(() => { alert.style.transition = 'opacity 0.5s ease'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }, 5000);
        }
    });
    // === Passwort Anzeigen/Verbergen Logik (MIT Klassen) ===
    const passwordToggles = document.querySelectorAll('.toggle-password-icon');
    passwordToggles.forEach(icon => {
        icon.addEventListener('click', function() {
            // Findet das Input-Element direkt vor dem Span
            const passwordInput = this.previousElementSibling;
            if (passwordInput && (passwordInput.tagName === 'INPUT' && (passwordInput.type === 'password' || passwordInput.type === 'text'))) {
                // Toggle Input-Typ
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    // Klassen umschalten
                    this.classList.remove('icon-eye-open');
                    this.classList.add('icon-eye-closed');
                    this.setAttribute('aria-label', 'Passwort verbergen');
                } else {
                    passwordInput.type = 'password';
                    // Klassen umschalten
                    this.classList.remove('icon-eye-closed');
                    this.classList.add('icon-eye-open');
                    this.setAttribute('aria-label', 'Passwort anzeigen');
                }
            } else {
                console.warn('Could not find password input element preceding the icon or element is not an input.');
            }
        });
        // Verhindert Fokusverlust beim Klick
        icon.addEventListener('mousedown', function(e) {
            e.preventDefault();
        });
    });
    // === ENDE Passwort Anzeigen/Verbergen Logik ===
    // --- Modal (Popup) Logic for profile actions ---
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        // Lock scroll
        document.body.style.overflow = 'hidden';
    }
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = ''; // restore
    }
    document.querySelectorAll('[data-modal-target]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const selector = this.getAttribute('data-modal-target');
            const modal = document.querySelector(selector);
            openModal(modal);
        });
    });
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    // Close modal on background click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this);
        });
    });
    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.open').forEach(m => closeModal(m));
        }
    });
    // --- 2FA Modal Sub-Views Logic ---
    function show2FAView(viewId) {
        const views = ['2fa-main', '2fa-setup', '2fa-change', '2fa-backup', '2fa-disable'];
        views.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = (id === viewId) ? 'block' : 'none';
        });
    }
    // Event listeners for 2FA buttons
    document.getElementById('btn-2fa-setup')?.addEventListener('click', () => show2FAView('2fa-setup'));
    document.getElementById('btn-2fa-change')?.addEventListener('click', () => show2FAView('2fa-change'));
    document.getElementById('btn-2fa-backup')?.addEventListener('click', () => show2FAView('2fa-backup'));
    document.getElementById('btn-2fa-disable')?.addEventListener('click', () => show2FAView('2fa-disable'));
    document.getElementById('btn-2fa-back')?.addEventListener('click', () => show2FAView('2fa-main'));
    // --- Global AJAX form handling for all pages (ajax-action) ---
    function showAjaxFlash(message, type = 'success') {
        let flashContainer = document.getElementById('ajaxFlashContainer');
        if (!flashContainer) {
            flashContainer = document.createElement('div');
            flashContainer.id = 'ajaxFlashContainer';
            flashContainer.className = 'ajax-toast-container';
            document.body.appendChild(flashContainer);
        }
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.setAttribute('role', 'alert');
        toast.textContent = message;
        flashContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }
    function handleAjaxResponse(data) {
        if (!data) {
            showAjaxFlash('Ungültige Serverantwort', 'error');
            return;
        }
        if (data.success) {
            showAjaxFlash(data.message || 'Erfolgreich', 'success');
            
            // Teile die Aktion mit anderen Tabs via localStorage
            try {
                const broadcastData = {
                    action: data.action,
                    timestamp: new Date().getTime(),
                    data: data.data
                };
                localStorage.setItem('fileActionBroadcast', JSON.stringify(broadcastData));
            } catch (e) {
                console.warn('localStorage nicht verfügbar:', e);
            }
            
            if (data.action === 'delete_file' && data.data?.file_id) {
                const row = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"]`);
                if (row) row.remove();
            }
            if (data.action === 'delete_permanently' && data.data?.file_id) {
                const row = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"]`);
                if (row) row.remove();
            }
            if (data.action === 'delete_folder' && data.data?.folder_id) {
                const row = document.querySelector(`tr.folder-row[data-folder-id="${data.data.folder_id}"]`);
                if (row) row.remove();
            }
            if (data.action === 'delete_user' && data.data?.user_id) {
                const row = document.querySelector(`tr[data-user-id="${data.data.user_id}"]`);
                if (row) row.remove();
            }
            if (data.action === 'restore_file' && data.data?.file_id) {
                const row = document.querySelector(`tr[data-file-id="${data.data.file_id}"]`);
                if (row) row.remove();
            }
            if (data.action === 'make_private' && data.data?.file_id) {
                // Auf public_files.php: Zeile entfernen, wenn File privatisiert wird
                const row = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"]`);
                if (row) row.remove();
            }
            if (data.action === 'toggle_public_status' && data.data?.file_id) {
                const row = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"]`);
                const isPublic = data.data.new_status == 1 || data.data.new_status === true;
                if (row) {
                    // Aktualisiere Status und Button
                    const cells = row.querySelectorAll('td');
                    let statusCell = null;

                    if (cells.length === 5) {
                        // Dashboard own_files Abschnitt: filename, date, size, status, actions
                        statusCell = cells[3]?.querySelector('.status-label');
                    } else if (cells.length >= 6) {
                        // Dashboard public_files/all_files: filename, date, uploader, size, status, actions
                        statusCell = cells[4]?.querySelector('.status-label');
                    }

                    if (statusCell) {
                        statusCell.className = `status-label ${isPublic ? 'status-public' : 'status-private'}`;
                        statusCell.textContent = isPublic ? 'Öffentlich' : 'Privat';
                    }

                    const actionBtn = row.querySelector('button[name="toggle_public_status"]');
                    if (actionBtn) {
                        actionBtn.className = `action-button ${isPublic ? 'private-button' : 'public-button'}`;
                        actionBtn.title = isPublic ? 'Privat machen' : 'Öffentlich machen';
                        actionBtn.textContent = isPublic ? '🔒' : '🌍';
                        const currentStatusInput = actionBtn.closest('form')?.querySelector('input[name="current_status"]');
                        if (currentStatusInput) currentStatusInput.value = isPublic ? '1' : '0';
                    }
                }

                // Zeile in public_files löschen, falls jetzt privat
                if (!isPublic && window.location.pathname.includes('/public_files')) {
                    row?.remove();
                }
            }
            if (data.action === 'rename_file' && data.data?.file_id && data.data?.new_name) {
                let nameCell = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"] .filename-cell a`);
                if (!nameCell) {
                    nameCell = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"] a`);
                }
                if (nameCell) nameCell.textContent = data.data.new_name;
            }
            if (data.action === 'rename_folder' && data.data?.folder_id && data.data?.new_name) {
                let nameCell = document.querySelector(`tr.folder-row[data-folder-id="${data.data.folder_id}"] .filename-cell a`);
                if (!nameCell) {
                    nameCell = document.querySelector(`tr.folder-row[data-folder-id="${data.data.folder_id}"] a`);
                }
                if (nameCell) nameCell.textContent = data.data.new_name;
            }
        } else {
            showAjaxFlash(data.message || 'Ein Fehler ist aufgetreten', 'error');
        }
    }
    function postAjaxAction(actionData) {
        const endpoint = window.location.pathname;
        return fetch(endpoint, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams(actionData) })
        .then(response => {
            return response.text().then(text => {
                if (!response.ok) {
                    console.error('AJAX Serverfehler:', response.status, response.statusText, text);
                    let message = `Serverfehler ${response.status}`;
                    try {
                        const parsed = JSON.parse(text);
                        if (parsed && parsed.message) message = parsed.message;
                    } catch (_e) {
                        // kein JSON, keine Änderung
                    }
                    throw new Error(message);
                }
                return text;
            });
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                handleAjaxResponse(data);
                return data;
            } catch (e) {
                console.error('AJAX JSON Fehler:', e, 'response:', text);
                showAjaxFlash('Ungültige Serverantwort (JSON).', 'error');
                throw e;
            }
        })
        .catch(error => {
            console.error('AJAX Fehler:', error);
            showAjaxFlash(`Netzwerkfehler: ${error.message}`, 'error');
        });
    }
    async function confirmAction(confirmText) {
        if (!confirmText) {
            return true;
        }
        if (typeof window.customConfirm === 'function') {
            return await window.customConfirm(confirmText);
        }
        return window.confirm(confirmText);
    }

    document.querySelectorAll('form.ajax-action').forEach(form => {
        form.addEventListener('submit', async function(e) {
            let confirmText = this.dataset.confirm || null;
            if (!confirmText && this.getAttribute('onsubmit')) {
                const onsubmitAttr = this.getAttribute('onsubmit');
                const match = onsubmitAttr.match(/return\s+confirm\(['\"](.+)['\"]\)/);
                if (match) {
                    confirmText = match[1];
                }
            }
            const confirmed = await confirmAction(confirmText);
            if (!confirmed) {
                return;
            }
            if (e.defaultPrevented) {
                // Falls onsubmit="return confirm(...)" bereits abgebrochen wurde
                return;
            }
            e.preventDefault();
            const formData = new FormData(this);
            formData.set('ajax', '1');

            // In einigen Browsern wird der Name/Value des Submit-Buttons nicht automatisch in FormData aufgenommen.
            // Hier absichern, damit serverseitiges `isset($_POST['delete_file'])` etc. zuverlässig funktioniert.
            const submitter = e.submitter || this.querySelector('button[type="submit"][name], input[type="submit"][name]');
            const actionField = this.querySelector('[name="delete_file"], [name="delete_folder"], [name="restore_file"], [name="delete_permanently"], [name="toggle_public_status"], [name="make_private"], [name="update_role"], [name="delete_user"], [name="rename_file"], [name="rename_folder"], [name="move_file"], [name="move_folder"]');

            if (submitter && submitter.name && !formData.has(submitter.name)) {
                const submitterValue = submitter.value !== undefined && submitter.value !== '' ? submitter.value : '1';
                formData.append(submitter.name, submitterValue);
            } else if (actionField && !formData.has(actionField.name)) {
                const actionValue = actionField.value !== undefined && actionField.value !== '' ? actionField.value : '1';
                formData.append(actionField.name, actionValue);
            }

            const actionName = actionField;
            if (actionName) {
                const payload = new URLSearchParams(formData);
                const endpoint = new URL(this.action, window.location.origin).href;
                fetch(endpoint, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: payload })
                .then(res => { 
                    if (!res.ok) { 
                        return res.text().then(text => { 
                            console.error('AJAX-Formular Serverfehler:', res.status, res.statusText, text); 
                            throw new Error(`HTTP ${res.status}: ${res.statusText}`); 
                        }); 
                    } 
                    return res.text(); 
                })
                .then(text => { 
                    if (!text || text.trim() === '') {
                        console.error('AJAX-Antwort ist leer');
                        showAjaxFlash('Leere Serverantwort', 'error');
                        return;
                    }
                    try { 
                        const data = JSON.parse(text); 
                        handleAjaxResponse(data); 
                    } catch (e) { 
                        console.error('AJAX-Formular JSON Fehler:', e, 'response:', text); 
                        showAjaxFlash('Ungültige Serverantwort (JSON): ' + e.message, 'error'); 
                    } 
                })
                .catch(err => { 
                    console.error('AJAX-Formular Fehler:', err); 
                    showAjaxFlash('Netzwerkfehler: ' + err.message, 'error'); 
                });
            } else {
                this.submit();
            }
        });
    });
    
    // --- Tab-Synchronisation via localStorage ---
    window.addEventListener('storage', (event) => {
        if (event.key === 'fileActionBroadcast' && event.newValue) {
            try {
                const broadcastData = JSON.parse(event.newValue);
                const action = broadcastData.action;
                const data = broadcastData.data;
                
                // Aktualisiere die aktuelle Seite basierend auf der Aktion aus einem anderen Tab
                if (action === 'delete_file' && data?.file_id) {
                    const row = document.querySelector(`tr.file-row[data-file-id="${data.file_id}"]`);
                    if (row) {
                        row.style.opacity = '0.5';
                        setTimeout(() => row.remove(), 300);
                    }
                }
                if (action === 'delete_folder' && data?.folder_id) {
                    const row = document.querySelector(`tr.folder-row[data-folder-id="${data.folder_id}"]`);
                    if (row) {
                        row.style.opacity = '0.5';
                        setTimeout(() => row.remove(), 300);
                    }
                }
                if (action === 'toggle_public_status' && data?.file_id) {
                    const row = document.querySelector(`tr.file-row[data-file-id="${data.file_id}"]`);
                    if (row) {
                        const cells = row.querySelectorAll('td');
                        const isPublic = data.new_status == 1 || data.new_status === true;
                        
                        if (cells.length === 5) {
                            const statusCell = cells[3]?.querySelector('.status-label');
                            if (statusCell) {
                                statusCell.className = `status-label ${isPublic ? 'status-public' : 'status-private'}`;
                                statusCell.textContent = isPublic ? 'Öffentlich' : 'Privat';
                            }
                        } else if (cells.length >= 6) {
                            const statusCell = cells[4]?.querySelector('.status-label');
                            if (statusCell) {
                                statusCell.className = `status-label ${isPublic ? 'status-public' : 'status-private'}`;
                                statusCell.textContent = isPublic ? 'Öffentlich' : 'Privat';
                            }
                        }
                        
                        const actionBtn = row.querySelector('button[name="toggle_public_status"]');
                        if (actionBtn) {
                            actionBtn.className = `action-button ${isPublic ? 'private-button' : 'public-button'}`;
                            actionBtn.title = isPublic ? 'Privat machen' : 'Öffentlich machen';
                            actionBtn.textContent = isPublic ? '🔒' : '🌍';
                        }

                        const currentStatusInput = row.querySelector('form input[name="current_status"]');
                        if (currentStatusInput) {
                            currentStatusInput.value = isPublic ? '1' : '0';
                        }
                    }
                }
                if (action === 'make_private' && data?.file_id) {
                    const row = document.querySelector(`tr.file-row[data-file-id="${data.file_id}"]`);
                    if (row) {
                        row.style.opacity = '0.5';
                        setTimeout(() => row.remove(), 300);
                    }
                }
                if (action === 'restore_file' && data?.file_id) {
                    const row = document.querySelector(`tr.file-row[data-file-id="${data.file_id}"]`);
                    if (row) {
                        row.style.opacity = '0.5';
                        setTimeout(() => row.remove(), 300);
                    }
                }
                console.log('Tab-Synchronisation: Aktion aktualisiert von anderem Tab:', action);
            } catch (e) {
                console.error('Fehler beim Parsen der Tab-Synchronisationsdaten:', e);
            }
        }
    });
}); // Ende DOMContentLoaded