/* School Management System - small, dependency-free front-end behaviour */
(function () {
    'use strict';

    // ---------- Mobile sidebar ----------
    var sidebar = document.getElementById('sidebar');
    var scrim = document.getElementById('scrim');
    var menuBtn = document.getElementById('menuBtn');
    function toggleMenu(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        scrim.classList.toggle('show', open);
    }
    if (menuBtn) menuBtn.addEventListener('click', function () { toggleMenu(!sidebar.classList.contains('open')); });
    if (scrim) scrim.addEventListener('click', function () { toggleMenu(false); });

    // ---------- Toast dismiss + auto-hide ----------
    document.querySelectorAll('.toast').forEach(function (t) {
        var x = t.querySelector('.toast-x');
        if (x) x.addEventListener('click', function () { t.remove(); });
        if (t.classList.contains('toast-success')) setTimeout(function () { t.remove(); }, 6000);
    });

    // ---------- Filters / pickers submit automatically ----------
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    // ---------- Add / edit / delete dialogs (manage.php) ----------
    var formModal = document.getElementById('formModal');
    var deleteModal = document.getElementById('deleteModal');

    document.querySelectorAll('dialog [data-close]').forEach(function (b) {
        b.addEventListener('click', function () { b.closest('dialog').close(); });
    });
    document.querySelectorAll('dialog.modal').forEach(function (d) {
        d.addEventListener('mousedown', function (e) { if (e.target === d) d.close(); });   // click on the dark backdrop
    });

    function openForm(mode, values) {
        if (!formModal) return;
        var form = formModal.querySelector('form');
        var singular = formModal.getAttribute('data-singular') || 'Record';
        var isEdit = (mode === 'update');

        form.reset();
        document.getElementById('formAction').value = isEdit ? 'update' : 'add';
        document.getElementById('formId').value = isEdit && values ? (values.__id || '') : '';
        document.getElementById('formTitle').textContent = (isEdit ? 'Edit ' : 'Add ') + singular;

        // Passwords: required when adding, optional (keep current) when editing
        form.querySelectorAll('[data-required-add]').forEach(function (inp) {
            inp.required = !isEdit;
            inp.placeholder = isEdit ? 'Leave blank to keep the current password' : 'At least 8 characters';
        });

        // Photo preview boxes
        form.querySelectorAll('.photo-current').forEach(function (box) {
            var file = values ? values[box.getAttribute('data-field')] : '';
            if (file) {
                box.querySelector('img').src = box.getAttribute('data-dir') + file;
                box.hidden = false;
            } else {
                box.hidden = true;
            }
        });

        if (values) {
            Object.keys(values).forEach(function (key) {
                var el = form.elements[key];
                if (!el || el.type === 'file' || el.type === 'password' || el.type === 'hidden') return;
                el.value = values[key];
            });
        }
        formModal.showModal();
        var first = form.querySelector('input:not([type=hidden]):not([type=file]), select, textarea');
        if (first) first.focus();
    }

    document.querySelectorAll('[data-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var err = document.getElementById('formError');
            if (err) err.hidden = true;
            openForm('add', null);
        });
    });
    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var err = document.getElementById('formError');
            if (err) err.hidden = true;
            openForm('update', JSON.parse(btn.getAttribute('data-edit')));
        });
    });
    document.querySelectorAll('[data-delete-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!deleteModal) return;
            document.getElementById('deleteId').value = btn.getAttribute('data-delete-id');
            document.getElementById('deleteName').textContent = btn.getAttribute('data-delete-name');
            deleteModal.showModal();
        });
    });

    // A failed save re-opens the form with what the user typed and the error message
    if (window.SMS_REOPEN) {
        openForm(window.SMS_REOPEN.mode, window.SMS_REOPEN.values);
        var e = document.getElementById('formError');
        if (e) e.hidden = false;
    }

    // ---------- Grades: live Final Grade / Equivalent / Remarks ----------
    var gradeTable = document.querySelector('[data-grade-table]');
    if (gradeTable) {
        var pass = parseFloat(gradeTable.getAttribute('data-pass')) || 75;
        var equiv = function (p) {
            if (p >= 96) return 1.00; if (p >= 94) return 1.25; if (p >= 91) return 1.50; if (p >= 88) return 1.75;
            if (p >= 85) return 2.00; if (p >= 82) return 2.25; if (p >= 79) return 2.50; if (p >= 76) return 2.75;
            if (p >= pass) return 3.00; return 5.00;
        };
        var recalc = function (tr) {
            var ins = tr.querySelectorAll('.grade-input');
            var vals = [], ok = true;
            ins.forEach(function (i) { if (i.value === '') ok = false; else vals.push(parseFloat(i.value)); });
            var avg = tr.querySelector('.cell-avg strong'), eq = tr.querySelector('.cell-eq'), rem = tr.querySelector('.cell-remarks');
            if (!ok || vals.length < 3) {
                avg.textContent = '—'; eq.textContent = '—';
                rem.innerHTML = '<span class="badge badge-amber">In Progress</span>';
                return;
            }
            var p = Math.round(((vals[0] + vals[1] + vals[2]) / 3) * 100) / 100;
            avg.textContent = p.toFixed(2);
            eq.textContent = equiv(p).toFixed(2);
            rem.innerHTML = p >= pass ? '<span class="badge badge-green">Passed</span>' : '<span class="badge badge-red">Failed</span>';
        };
        gradeTable.addEventListener('input', function (ev) {
            if (ev.target.classList.contains('grade-input')) recalc(ev.target.closest('tr'));
        });
    }

    // ---------- Attendance: mark everyone present ----------
    document.querySelectorAll('[data-mark-all]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var status = btn.getAttribute('data-mark-all');
            document.querySelectorAll('.seg input[value="' + status + '"]').forEach(function (r) { r.checked = true; });
        });
    });
})();
