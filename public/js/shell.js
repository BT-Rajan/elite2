/**
 * Elite 2.0 — App Shell
 * Renders the sidebar + topbar, handles navigation, mobile toggle.
 * Import on every authenticated page.
 */

import api, { store } from './api.js';
import ui from './ui.js';

const NAV = {
  admin: [
    { href: _root + '/pages/admin-dashboard.html', icon: '⊞', label: 'Dashboard' },
    { href: _root + '/pages/users.html',            icon: '👥', label: 'Users' },
    { href: _root + '/pages/disciplines.html',      icon: '🥋', label: 'Curriculum' },
    { href: _root + '/pages/coaches.html',          icon: '🏅', label: 'Coaches' },
    { href: _root + '/pages/settings.html',         icon: '⚙', label: 'Settings' },
    { href: _root + '/pages/notifications.html',    icon: '🔔', label: 'Notifications' },
  ],
  head_coach: [
    { href: _root + '/pages/hc-dashboard.html',     icon: '⊞', label: 'Dashboard' },
    { href: _root + '/pages/students.html',         icon: '👤', label: 'Students' },
    { href: _root + '/pages/coaches.html',          icon: '🏅', label: 'Coaches' },
    { href: _root + '/pages/disciplines.html',      icon: '🥋', label: 'Curriculum' },
    { href: _root + '/pages/evaluations.html',      icon: '📋', label: 'Evaluations' },
    { href: _root + '/pages/promotions.html',       icon: '⬆', label: 'Promotions' },
    { href: _root + '/pages/seminars.html',         icon: '🎓', label: 'Seminars' },
    { href: _root + '/pages/schedule.html',         icon: '📅', label: 'Schedule' },
    { href: _root + '/pages/reports.html',          icon: '📊', label: 'Reports' },
    { href: _root + '/pages/notifications.html',    icon: '🔔', label: 'Notifications' },
  ],
  coach: [
    { href: _root + '/pages/coach-dashboard.html',  icon: '⊞', label: 'Dashboard' },
    { href: _root + '/pages/schedule.html',         icon: '📅', label: 'My Classes' },
    { href: _root + '/pages/attendance.html',       icon: '✓',  label: 'Attendance' },
    { href: _root + '/pages/students.html',         icon: '👤', label: 'Students' },
    { href: _root + '/pages/evaluations.html',      icon: '📋', label: 'Evaluations' },
    { href: _root + '/pages/notifications.html',    icon: '🔔', label: 'Notifications' },
  ],
  student: [
    { href: _root + '/pages/student-dashboard.html',icon: '⊞', label: 'Dashboard' },
    { href: _root + '/pages/my-progress.html',      icon: '📈', label: 'My Progress' },
    { href: _root + '/pages/my-attendance.html',    icon: '✓',  label: 'Attendance' },
    { href: _root + '/pages/belt-progress.html',    icon: '🥋', label: 'Belt Progress' },
    { href: _root + '/pages/achievements.html',     icon: '🏆', label: 'Achievements' },
    { href: _root + '/pages/schedule.html',         icon: '📅', label: 'Schedule' },
    { href: _root + '/pages/notifications.html',    icon: '🔔', label: 'Notifications' },
  ],
};

/**
 * Initialise the app shell on an authenticated page.
 * @param {{ title?: string }} opts
 */
async function initShell(opts = {}) {
  const user = store.user;
  if (!user) {
    window.location.href = _root + '/login.html';
    return;
  }

  _renderSidebar(user);
  _renderTopbar(opts.title || document.title);
  _highlightActive();
  _initMobileToggle();
  await _loadNotificationCount();
}

function _renderSidebar(user) {
  const nav = NAV[user.role] || [];
  const path = window.location.pathname;

  const sidebar = document.createElement('aside');
  sidebar.className = 'sidebar';
  sidebar.id = 'sidebar';

  sidebar.innerHTML = `
    <div class="sidebar-logo">
      <div class="sidebar-logo-mark">E2</div>
      <div>
        <div class="sidebar-logo-text">Elite 2.0</div>
        <div class="sidebar-logo-sub">Academy</div>
      </div>
    </div>
    <nav class="sidebar-nav" id="sidebar-nav">
      ${nav.map(item => `
        <a href="${item.href}" class="nav-item${path.endsWith(item.href.split('/').pop()) ? ' active' : ''}" data-href="${item.href}">
          <span class="nav-icon">${item.icon}</span>
          <span>${item.label}</span>
          ${item.label === 'Notifications' ? '<span class="nav-badge hidden" id="notif-badge">0</span>' : ''}
        </a>`).join('')}
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user" id="user-menu-btn" title="Account menu">
        ${ui.avatar({ name: user.first_name + ' ' + user.last_name, avatar: user.avatar, size: 'sm' })}
        <div>
          <div class="sidebar-user-name">${user.first_name} ${user.last_name}</div>
          <div class="sidebar-user-role">${user.role.replace('_', ' ')}</div>
        </div>
      </div>
    </div>
  `;

  document.body.prepend(sidebar);

  // User menu dropdown
  document.getElementById('user-menu-btn').addEventListener('click', _showUserMenu);
}

function _renderTopbar(title) {
  const topbar = document.createElement('header');
  topbar.className = 'topbar';
  topbar.innerHTML = `
    <button class="btn btn-ghost btn-icon" id="sidebar-toggle" aria-label="Toggle menu">☰</button>
    <div class="topbar-title">${title}</div>
    <div class="topbar-actions" id="topbar-actions"></div>
  `;
  document.querySelector('.main')?.prepend(topbar);
}

function _highlightActive() {
  const path = window.location.pathname;
  document.querySelectorAll('.nav-item').forEach(a => {
    const href = a.getAttribute('data-href') || a.getAttribute('href');
    if (href && path.endsWith(href.split('/').pop())) {
      a.classList.add('active');
    } else {
      a.classList.remove('active');
    }
  });
}

function _initMobileToggle() {
  const btn = document.getElementById('sidebar-toggle');
  const sidebar = document.getElementById('sidebar');
  if (!btn || !sidebar) return;

  btn.addEventListener('click', () => sidebar.classList.toggle('open'));

  // Close on outside click
  document.addEventListener('click', e => {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== btn) {
      sidebar.classList.remove('open');
    }
  });
}

async function _loadNotificationCount() {
  const { data } = await api.notifications.list({ unread: true });
  const count = data?.unread_count || 0;
  const badge = document.getElementById('notif-badge');
  if (badge && count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.classList.remove('hidden');
  }
}

function _showUserMenu() {
  const existing = document.getElementById('user-dropdown');
  if (existing) { existing.remove(); return; }

  const menu = document.createElement('div');
  menu.className = 'dropdown-menu';
  menu.id = 'user-dropdown';
  menu.style.cssText = 'position:fixed;bottom:72px;left:16px;width:200px;';

  menu.innerHTML = `
    <a href="${_root}/pages/profile.html" class="dropdown-item">👤 Profile</a>
    <div class="divider" style="margin:4px 0"></div>
    <div class="dropdown-item danger" id="logout-btn">🚪 Sign out</div>
  `;

  document.body.appendChild(menu);

  document.getElementById('logout-btn').addEventListener('click', async () => {
    await api.auth.logout();
    store.clear();
    window.location.href = _root + '/login.html';
  });

  setTimeout(() => {
    document.addEventListener('click', function close(e) {
      if (!menu.contains(e.target)) { menu.remove(); document.removeEventListener('click', close); }
    });
  }, 10);
}

export { initShell };
