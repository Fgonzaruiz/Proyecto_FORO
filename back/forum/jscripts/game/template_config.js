(function() {
    function init() {
    var pageRoot = document.querySelector('.rpg-templates-page[data-active-char-id]');
    var editorPanel = document.getElementById('template-editor-panel');
    var templateList = document.getElementById('template-list');
    var editorArea = document.getElementById('template-editor-area');
    var nameInput = document.getElementById('template-name-input');
    var textarea = document.getElementById('template-editor-textarea');
    var saveBtn = document.getElementById('template-save-btn');
    var cancelBtn = document.getElementById('template-cancel-btn');
    var saveStatus = document.getElementById('template-save-status');
    var previewPanel = document.getElementById('template-preview-panel');
    var previewContent = document.getElementById('template-preview-content');
    var addBtn = document.getElementById('template-add-btn');

    if (!pageRoot || !editorPanel) {
        return;
    }

    var currentCharId = parseInt(pageRoot.getAttribute('data-active-char-id'), 10) || 0;
    if (!currentCharId) {
        return;
    }

    var templates = [];
    var editingIndex = -1;
    var previewTimer = null;

    function buildBaseUrl() {
        var nav = document.getElementById('pj-nav-submenu');
        if (nav && nav.dataset.base) return nav.dataset.base;
        return window.location.origin;
    }
    var baseUrl = buildBaseUrl();

    var sceditorInstance = null;
    function initSceditor() {
        if (typeof window.sceditor === 'undefined') return false;
        if (sceditorInstance) return true;
        try {
            if (textarea.parentNode.querySelector('.sceditor-container')) return true;
            sceditorInstance = window.sceditor.instance(textarea);
            if (sceditorInstance) return true;
        } catch(e) {}
        try {
            sceditorInstance = window.sceditor.create(textarea, {
                format: 'bbcode',
                toolbar: 'bold,italic,underline,strike|size,color|bulletlist,orderedlist|image,link,unlink|table|code,quote|horizontalrule',
                style: baseUrl + '/jscripts/editor_styles.css?1',
                emoticonsEnabled: false,
                width: '100%'
            });
            sceditorInstance && sceditorInstance.data('rpg-template', true);
            return true;
        } catch(e) {
            return false;
        }
    }

    function getEditorContent() {
        if (sceditorInstance && typeof sceditorInstance.val === 'function') {
            return sceditorInstance.val();
        }
        return textarea.value;
    }

    function setEditorContent(html) {
        if (sceditorInstance && typeof sceditorInstance.val === 'function') {
            sceditorInstance.val(html);
        }
        textarea.value = html;
    }

    function destroySceditor() {
        if (sceditorInstance && typeof sceditorInstance.destroy === 'function') {
            try { sceditorInstance.destroy(); } catch(e) {}
        }
        sceditorInstance = null;
    }

    function renderTemplateList() {
        templateList.innerHTML = '';
        if (!templates.length) {
            templateList.innerHTML = '<div class="rpg-template-empty"><i class="fas fa-scroll"></i> Sin templates a\u00fan. Crea uno nuevo.</div>';
            return;
        }
        templates.forEach(function(t, i) {
            var item = document.createElement('div');
            item.className = 'rpg-template-item' + (i === editingIndex ? ' is-editing' : '');
            item.innerHTML =
                '<div class="rpg-template-item-name"><i class="fas fa-scroll"></i> ' + escapeHtml(t.name || 'Sin nombre') + '</div>' +
                '<div class="rpg-template-item-actions">' +
                    '<button type="button" class="rpg-template-item-btn rpg-template-item-btn--edit" data-index="' + i + '" title="Editar"><i class="fas fa-pen"></i></button>' +
                    '<button type="button" class="rpg-template-item-btn rpg-template-item-btn--delete" data-index="' + i + '" title="Eliminar"><i class="fas fa-trash-alt"></i></button>' +
                '</div>';
            templateList.appendChild(item);
        });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function loadTemplates() {
        editingIndex = -1;
        editorArea.classList.add('is-hidden');
        previewPanel.classList.add('is-hidden');
        saveBtn.disabled = true;
        saveStatus.textContent = 'Cargando...';

        fetch(baseUrl + '/game/ajax/post_template.php?character_id=' + currentCharId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    templates = d.data.templates || [];
                    renderTemplateList();
                    saveStatus.textContent = '';
                } else {
                    saveStatus.textContent = 'Error al cargar: ' + (d.error ? d.error.message : 'desconocido');
                }
            })
            .catch(function() {
                saveStatus.textContent = 'Error de conexi\u00f3n';
            });
    }

    addBtn.addEventListener('click', function() {
        editingIndex = -1;
        nameInput.value = '';
        setEditorContent('');
        editorArea.classList.remove('is-hidden');
        previewPanel.classList.add('is-hidden');
        saveBtn.disabled = true;
        saveStatus.textContent = '';
        nameInput.focus();
        initSceditor();
        renderTemplateList();
    });

    cancelBtn.addEventListener('click', function() {
        editorArea.classList.add('is-hidden');
        previewPanel.classList.add('is-hidden');
        editingIndex = -1;
        renderTemplateList();
    });

    templateList.addEventListener('click', function(e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var index = parseInt(btn.dataset.index);
        if (isNaN(index) || index < 0 || index >= templates.length) return;

        if (btn.classList.contains('rpg-template-item-btn--edit')) {
            editingIndex = index;
            nameInput.value = templates[index].name || '';
            setEditorContent(templates[index].content || '');
            editorArea.classList.remove('is-hidden');
            previewPanel.classList.add('is-hidden');
            saveBtn.disabled = false;
            saveStatus.textContent = 'Editando template...';
            initSceditor();
            renderTemplateList();
        }

        if (btn.classList.contains('rpg-template-item-btn--delete')) {
            if (!confirm('\u00bfEliminar el template "' + (templates[index].name || '') + '"?')) return;
            templates.splice(index, 1);
            if (editingIndex === index) {
                editorArea.classList.add('is-hidden');
                previewPanel.classList.add('is-hidden');
                editingIndex = -1;
            } else if (editingIndex > index) {
                editingIndex--;
            }
            renderTemplateList();
            saveTemplates();
        }
    });

    function onContentChange() {
        var name = nameInput.value.trim();
        var content = getEditorContent().trim();
        var hasChanges = true;
        if (editingIndex >= 0 && editingIndex < templates.length) {
            hasChanges = name !== (templates[editingIndex].name || '') || content !== (templates[editingIndex].content || '');
        } else {
            hasChanges = name !== '' || content !== '';
        }
        saveBtn.disabled = !hasChanges;
        if (hasChanges) {
            saveStatus.textContent = editingIndex >= 0 ? 'Template modificado.' : 'Nuevo template listo para guardar.';
        } else {
            saveStatus.textContent = '';
        }
        updatePreview();
    }

    nameInput.addEventListener('input', onContentChange);
    textarea.addEventListener('input', onContentChange);

    function updatePreview() {
        var text = getEditorContent().trim();
        if (!text) {
            previewPanel.classList.add('is-hidden');
            return;
        }
        previewPanel.classList.remove('is-hidden');
        previewContent.innerHTML = '<div class="rpg-preview-loading"><i class="fas fa-spinner fa-spin"></i> Renderizando...</div>';

        clearTimeout(previewTimer);
        previewTimer = setTimeout(function() {
            fetch(baseUrl + '/game/ajax/preview_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: text })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok && d.data && d.data.html) {
                    previewContent.innerHTML = d.data.html;
                } else {
                    previewContent.textContent = text;
                }
            })
            .catch(function() {
                previewContent.textContent = text;
            });
        }, 400);
    }

    function saveTemplates() {
        var csrfToken = window.GAME_CSRF || '';
        saveBtn.disabled = true;
        saveStatus.textContent = 'Guardando...';
        fetch(baseUrl + '/game/ajax/post_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                character_id: currentCharId,
                templates: templates,
                my_post_key: csrfToken
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                saveStatus.textContent = '';
                renderTemplateList();
                if (!editorArea.classList.contains('is-hidden') && editingIndex === -1) {
                    editorArea.classList.add('is-hidden');
                    previewPanel.classList.add('is-hidden');
                }
            } else {
                saveBtn.disabled = false;
                saveStatus.textContent = 'Error: ' + (d.error ? d.error.message : 'desconocido');
            }
        })
        .catch(function() {
            saveBtn.disabled = false;
            saveStatus.textContent = 'Error de conexi\u00f3n';
        });
    }

    saveBtn.addEventListener('click', function() {
        if (saveBtn.disabled || !currentCharId) return;
        var name = nameInput.value.trim();
        var content = getEditorContent().trim();
        if (!name) { alert('El nombre del template es obligatorio.'); return; }

        var templateObj = { name: name, content: content };

        if (editingIndex >= 0 && editingIndex < templates.length) {
            templates[editingIndex] = templateObj;
        } else {
            templates.push(templateObj);
        }
        saveTemplates();
    });

    loadTemplates();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
