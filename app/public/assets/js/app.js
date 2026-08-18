/* SportsMIS® Regatta — Application JavaScript */

'use strict';

/* ──────────────────────────────────────────────────────────────────────────
 * Shared toast. Every AJAX form in the app reports through this so success
 * and failure look the same wherever they happen.
 * ────────────────────────────────────────────────────────────────────────── */
window.rgToast = function (message, ok) {
  var host = document.getElementById('rgToastHost');
  if (!host) {
    host = document.createElement('div');
    host.id = 'rgToastHost';
    host.className = 'toast-container position-fixed top-0 end-0 p-3';
    host.style.zIndex = '9999';
    document.body.appendChild(host);
  }
  var el = document.createElement('div');
  el.className = 'toast align-items-center border-0 ' + (ok === false ? 'text-bg-danger' : 'text-bg-dark');
  el.setAttribute('role', 'alert');
  el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
    '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  el.querySelector('.toast-body').textContent = message;
  host.appendChild(el);
  if (window.bootstrap) {
    var t = new bootstrap.Toast(el, { delay: 3000 });
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    t.show();
  } else {
    alert(message);
  }
};

/* POST a FormData payload to a JSON endpoint. Always carries the CSRF token
 * from the page's <meta name="csrf-token">, so callers never repeat it. */
window.rgPost = async function (url, data) {
  var fd = data instanceof FormData ? data : new FormData();
  if (!(data instanceof FormData) && data) {
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
  }
  if (!fd.has('_token')) {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) fd.append('_token', meta.content);
  }
  var res = await fetch(url, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  try {
    return await res.json();
  } catch (err) {
    return { success: false, message: 'Unexpected server response (' + res.status + ').' };
  }
};

/* ── Auto-dismiss flash alerts ──────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.alert.fade.show').forEach(function (el) {
    setTimeout(function () {
      if (window.bootstrap) bootstrap.Alert.getOrCreateInstance(el).close();
    }, 5000);
  });
});

/* ── Confirm destructive actions ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});

/* ── AJAX section-save forms (data-ajax-form) ───────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-ajax-form]').forEach(function (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var btn = form.querySelector('[type=submit]');
      if (btn) btn.disabled = true;
      try {
        var data = await window.rgPost(form.getAttribute('action'), new FormData(form));
        window.rgToast(data.message || (data.success ? 'Saved.' : 'Could not save.'), data.success);
        if (data.success && data.redirect) window.location.href = data.redirect;
        else if (data.success && form.dataset.ajaxForm === 'reload') window.location.reload();
      } catch (err) {
        window.rgToast('Network error while saving.', false);
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  });
});

/* ── Live row count + client-side table filter ───────────────────────────
 * Any table with data-filter-table="<id>" is filtered by the inputs and
 * selects that carry data-filter-for="<id>"; the element with
 * data-filter-count="<id>" shows "showing N of M". */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-filter-table]').forEach(function (table) {
    var key      = table.dataset.filterTable;
    var controls = document.querySelectorAll('[data-filter-for="' + key + '"]');
    var counter  = document.querySelector('[data-filter-count="' + key + '"]');

    function apply() {
      var rows = table.tBodies[0] ? table.tBodies[0].rows : [];
      var shown = 0;
      for (var i = 0; i < rows.length; i++) {
        var row = rows[i], visible = true;
        controls.forEach(function (c) {
          if (!visible) return;
          var v = (c.value || '').trim().toLowerCase();
          if (v === '') return;
          if (c.dataset.filterField) {
            var cell = (row.dataset[c.dataset.filterField] || '').toLowerCase();
            if (cell !== v) visible = false;
          } else {
            if (row.textContent.toLowerCase().indexOf(v) === -1) visible = false;
          }
        });
        row.classList.toggle('d-none', !visible);
        if (visible) shown++;
      }
      if (counter) counter.textContent = 'Showing ' + shown + ' of ' + rows.length;
    }

    controls.forEach(function (c) {
      c.addEventListener('input', apply);
      c.addEventListener('change', apply);
    });
    var clear = document.querySelector('[data-filter-clear="' + key + '"]');
    if (clear) clear.addEventListener('click', function () {
      controls.forEach(function (c) { c.value = ''; });
      apply();
    });
    apply();
  });
});

/* ── Sortable tables (click a th[data-sort]) ────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('table[data-sortable]').forEach(function (table) {
    table.querySelectorAll('th[data-sort]').forEach(function (th) {
      th.style.cursor = 'pointer';
      th.addEventListener('click', function () {
        var field = th.dataset.sort;
        var asc   = th.dataset.dir !== 'asc';
        table.querySelectorAll('th[data-sort]').forEach(function (o) { delete o.dataset.dir; });
        th.dataset.dir = asc ? 'asc' : 'desc';
        var body = table.tBodies[0];
        var rows = Array.prototype.slice.call(body.rows);
        rows.sort(function (a, b) {
          var av = a.dataset[field] || '', bv = b.dataset[field] || '';
          var an = parseFloat(av), bn = parseFloat(bv);
          var cmp = (!isNaN(an) && !isNaN(bn)) ? an - bn : av.localeCompare(bv);
          return asc ? cmp : -cmp;
        });
        rows.forEach(function (r) { body.appendChild(r); });
      });
    });
  });
});

/* ── File input guard + image preview ───────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[type=file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = this.files[0];
      if (!file) return;
      var maxMb = this.dataset.maxMb ? parseFloat(this.dataset.maxMb) : 7;
      if (file.size > maxMb * 1024 * 1024) {
        window.rgToast('File is too large. Maximum ' + maxMb + ' MB allowed.', false);
        this.value = '';
        return;
      }
      var previewId = this.dataset.preview;
      if (previewId) {
        var preview = document.getElementById(previewId);
        if (preview) {
          var reader = new FileReader();
          reader.onload = function (ev) { preview.src = ev.target.result; preview.classList.remove('d-none'); };
          reader.readAsDataURL(file);
        }
      }
    });
  });
});

/* ── Date range: keep "to" at or after "from" ───────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  var from = document.getElementById('start_date');
  var to   = document.getElementById('end_date');
  if (!from || !to) return;
  from.addEventListener('change', function () {
    if (to.value && to.value < from.value) to.value = from.value;
    to.min = from.value;
  });
});

/* ── Tooltips ───────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  if (!window.bootstrap) return;
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    new bootstrap.Tooltip(el);
  });
});
