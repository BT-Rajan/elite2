/**
 * Elite 2.0 — UI Helpers
 * Reusable components: toast, modal, skeleton, confirm dialog, avatar.
 * No inline styles — all classes come from styles.css.
 */

// ── Toast ──────────────────────────────────────────────────────────────────────

let _toastContainer = null;

function _getToastContainer() {
  if (!_toastContainer) {
    _toastContainer = document.createElement('div');
    _toastContainer.className = 'toast-container';
    document.body.appendChild(_toastContainer);
  }
  return _toastContainer;
}

/**
 * Show a toast notification.
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {number} duration  ms (0 = persistent)
 */
function toast(message, type = 'info', duration = 4000) {
  const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.innerHTML = `<span>${icons[type] || icons.info}</span><span>${message}</span>`;

  const container = _getToastContainer();
  container.appendChild(el);

  if (duration > 0) {
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      el.style.transition = 'opacity 0.2s, transform 0.2s';
      setTimeout(() => el.remove(), 200);
    }, duration);
  }

  return el;
}

const ui = { toast };

ui.toast.success = (msg, d) => toast(msg, 'success', d);
ui.toast.error   = (msg, d) => toast(msg, 'error',   d);
ui.toast.warning = (msg, d) => toast(msg, 'warning', d);
ui.toast.info    = (msg, d) => toast(msg, 'info',    d);

// ── Modal ──────────────────────────────────────────────────────────────────────

/**
 * Open a modal.
 * @param {{ title, content, size?, onClose? }} opts
 * @returns {{ el, close }}
 */
ui.modal = function(opts) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';

  const modal = document.createElement('div');
  modal.className = `modal${opts.size ? ' modal-' + opts.size : ''}`;

  modal.innerHTML = `
    <div class="modal-header">
      <span class="modal-title">${opts.title || ''}</span>
      <button class="btn btn-ghost btn-icon" id="modal-close-btn" aria-label="Close">✕</button>
    </div>
    <div class="modal-body"></div>
  `;

  const body = modal.querySelector('.modal-body');
  if (typeof opts.content === 'string') {
    body.innerHTML = opts.content;
  } else if (opts.content instanceof HTMLElement) {
    body.appendChild(opts.content);
  }

  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  const close = () => {
    overlay.remove();
    opts.onClose?.();
  };

  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  modal.querySelector('#modal-close-btn').addEventListener('click', close);

  document.addEventListener('keydown', function esc(e) {
    if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
  });

  return { el: modal, overlay, body, close };
};

// ── Confirm dialog ─────────────────────────────────────────────────────────────

/**
 * Show a confirm dialog.
 * @param {{ title, message, confirmText?, danger? }} opts
 * @returns {Promise<boolean>}
 */
ui.confirm = function(opts) {
  return new Promise(resolve => {
    const { close, body } = ui.modal({ title: opts.title || 'Confirm', size: 'sm' });

    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <p class="text-sm text-muted" style="margin-bottom:1.5rem">${opts.message || ''}</p>
      <div class="modal-footer">
        <button class="btn btn-secondary" id="confirm-cancel">Cancel</button>
        <button class="btn ${opts.danger ? 'btn-danger' : 'btn-primary'}" id="confirm-ok">${opts.confirmText || 'Confirm'}</button>
      </div>
    `;

    wrap.querySelector('#confirm-cancel').onclick = () => { close(); resolve(false); };
    wrap.querySelector('#confirm-ok').onclick     = () => { close(); resolve(true); };
    body.appendChild(wrap);
  });
};

// ── Skeleton ───────────────────────────────────────────────────────────────────

/**
 * Replace element's content with skeleton loaders while data loads.
 * @param {HTMLElement} el
 * @param {string} type  'card'|'table'|'list'
 * @param {number} count  Number of rows/cards
 */
ui.skeleton = function(el, type = 'list', count = 4) {
  const templates = {
    card: () => `<div class="skeleton skeleton-card" style="margin-bottom:1rem"></div>`,
    list: () => `
      <div class="flex items-center gap-3" style="margin-bottom:0.75rem">
        <div class="skeleton skeleton-avatar"></div>
        <div class="flex-1">
          <div class="skeleton skeleton-text" style="width:60%;margin-bottom:6px"></div>
          <div class="skeleton skeleton-text" style="width:40%"></div>
        </div>
      </div>`,
    table: () => `<div class="skeleton skeleton-row" style="margin-bottom:4px"></div>`,
  };

  const tpl = templates[type] || templates.list;
  el.innerHTML = Array.from({ length: count }, tpl).join('');
};

// ── Avatar ─────────────────────────────────────────────────────────────────────

/**
 * Generate avatar HTML.
 * @param {{ name, avatar, size? }} opts
 * @returns {string}
 */
ui.avatar = function(opts) {
  const size   = opts.size || 'md';
  const initials = (opts.name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
  if (opts.avatar) {
    return `<div class="avatar avatar-${size}"><img src="${opts.avatar}" alt="${opts.name}" loading="lazy"></div>`;
  }
  return `<div class="avatar avatar-${size}">${initials}</div>`;
};

// ── Belt color dot ─────────────────────────────────────────────────────────────

ui.beltDot = function(color, name) {
  return `<span class="belt-dot" style="background:${color || '#888'}" title="${name || ''}"></span>`;
};

// ── Badge ──────────────────────────────────────────────────────────────────────

ui.badge = function(text, variant = 'gray') {
  return `<span class="badge badge-${variant}">${text}</span>`;
};

// ── Progress bar ───────────────────────────────────────────────────────────────

ui.progressBar = function(pct, variant) {
  const cls = pct >= 80 ? 'success' : pct >= 50 ? '' : 'danger';
  return `
    <div class="progress-bar">
      <div class="progress-fill ${variant || cls}" style="width:${Math.min(100, pct)}%"></div>
    </div>`;
};

// ── Empty state ────────────────────────────────────────────────────────────────

ui.empty = function(icon, title, desc, actionHTML = '') {
  return `
    <div class="empty-state">
      <div class="empty-icon">${icon}</div>
      <div class="empty-title">${title}</div>
      ${desc ? `<div class="empty-desc">${desc}</div>` : ''}
      ${actionHTML}
    </div>`;
};

// ── Loading button state ───────────────────────────────────────────────────────

ui.setLoading = function(btn, loading, originalText) {
  if (loading) {
    btn.disabled = true;
    btn._originalText = btn.innerHTML;
    btn.innerHTML = `<span class="spin" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%"></span>`;
  } else {
    btn.disabled = false;
    btn.innerHTML = originalText || btn._originalText || btn.innerHTML;
  }
};

// ── Date formatting ────────────────────────────────────────────────────────────

ui.date = function(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

ui.dateTime = function(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

ui.relativeTime = function(dateStr) {
  if (!dateStr) return '—';
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1)   return 'just now';
  if (mins < 60)  return `${mins}m ago`;
  if (mins < 1440) return `${Math.floor(mins/60)}h ago`;
  return `${Math.floor(mins/1440)}d ago`;
};

// ── Attendance status badge ────────────────────────────────────────────────────

ui.attendanceBadge = function(status) {
  const map = { present: 'green', absent: 'red', late: 'amber' };
  return ui.badge(status, map[status] || 'gray');
};

// ── Skill level badge ──────────────────────────────────────────────────────────

ui.skillBadge = function(level) {
  const map = {
    not_started: ['gray',   'Not Started'],
    developing:  ['amber',  'Developing'],
    competent:   ['blue',   'Competent'],
    advanced:    ['green',  'Advanced'],
  };
  const [variant, label] = map[level] || ['gray', level];
  return ui.badge(label, variant);
};

// ── Role badge ─────────────────────────────────────────────────────────────────

ui.roleBadge = function(role) {
  const map = { admin: 'red', head_coach: 'purple', coach: 'blue', student: 'green' };
  return ui.badge(role.replace('_', ' '), map[role] || 'gray');
};

export default ui;
