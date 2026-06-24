<?php
/**
 * Elite 2.0 — patch4.php
 * Fixes:
 *  1. login.html  — add show/hide password toggle button
 *  2. styles.css  — logout button: fix color inheritance so it doesn't look like a browser default button
 *  3. hc-dashboard.html — page was blank; rewrite to static skeleton HTML (like admin-dashboard)
 *                         + fix error path that left #panels as stale skeleton
 *
 * Idempotent. Run from XAMPP htdocs/elite2 (or any directory that has public/ beside this script).
 * Usage: php patch4.php
 */

define('BASE', __DIR__ . '/public');

$errors = [];
$fixed  = [];

// ─────────────────────────────────────────────────────────────────
// Helper
// ─────────────────────────────────────────────────────────────────
function patch(string $file, string $search, string $replace, string $label): void
{
    global $errors, $fixed;
    if (!file_exists($file)) {
        $errors[] = "MISSING: $file";
        return;
    }
    $src = file_get_contents($file);
    if (strpos($src, $replace) !== false) {
        $fixed[] = "SKIP (already applied): $label";
        return;
    }
    if (strpos($src, $search) === false) {
        $errors[] = "MARKER NOT FOUND in $file — $label";
        return;
    }
    $result = str_replace($search, $replace, $src);
    file_put_contents($file, $result);
    $fixed[] = "OK: $label";
}

function overwrite(string $file, string $content, string $label): void
{
    global $errors, $fixed;
    if (!file_exists($file)) {
        $errors[] = "MISSING: $file";
        return;
    }
    file_put_contents($file, $content);
    $fixed[] = "OK: $label";
}

// ═════════════════════════════════════════════════════════════════
// FIX 1 — login.html: show/hide password toggle
// ═════════════════════════════════════════════════════════════════

// 1a. Wrap the password input in a relative-positioned div and add a toggle button
patch(
    BASE . '/login.html',
    <<<'SEARCH'
        <input type="password" id="password" class="input" placeholder="••••••••" autocomplete="current-password" required>
SEARCH,
    <<<'REPLACE'
        <div style="position:relative">
          <input type="password" id="password" class="input" placeholder="••••••••" autocomplete="current-password" required style="padding-right:2.75rem">
          <button type="button" id="toggle-pw" aria-label="Show password"
            style="position:absolute;right:0.625rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-3);padding:0.25rem;line-height:1;font-size:1rem"
            onmouseenter="this.style.color='var(--color-text-2)'" onmouseleave="this.style.color='var(--color-text-3)'">
            👁
          </button>
        </div>
REPLACE,
    'login.html — password field: wrap + add toggle button'
);

// 1b. Add the toggle JS logic (after the existing `pass` variable declaration)
patch(
    BASE . '/login.html',
    "  const pass  = document.getElementById('password');",
    <<<'REPLACE'
  const pass  = document.getElementById('password');
  const togglePw = document.getElementById('toggle-pw');
  if (togglePw) {
    togglePw.addEventListener('click', () => {
      const shown = pass.type === 'text';
      pass.type = shown ? 'password' : 'text';
      togglePw.textContent = shown ? '👁' : '🙈';
      togglePw.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
    });
  }
REPLACE,
    'login.html — password toggle JS handler'
);

// ═════════════════════════════════════════════════════════════════
// FIX 2 — styles.css: logout button color
// The button has background:none/border:none but misses an explicit
// color reset, so some browsers render it with UA stylesheet (blue/black).
// Also add the `font-family` inherit so it matches the sidebar text.
// ═════════════════════════════════════════════════════════════════
patch(
    BASE . '/css/styles.css',
    <<<'SEARCH'
.sidebar-logout {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-2) var(--space-2);
  margin-top: var(--space-1);
  border-radius: var(--radius-md);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--color-text-2);
  font-size: var(--text-sm);
  transition: background var(--transition-fast);
  text-align: left;
}
SEARCH,
    <<<'REPLACE'
.sidebar-logout {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-2) var(--space-2);
  margin-top: var(--space-1);
  border-radius: var(--radius-md);
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--color-text-2);
  font-size: var(--text-sm);
  font-family: var(--font-ui);
  -webkit-appearance: none;
  appearance: none;
  transition: background var(--transition-fast), color var(--transition-fast);
  text-align: left;
}
REPLACE,
    'styles.css — sidebar-logout: fix button appearance & font'
);

// ═════════════════════════════════════════════════════════════════
// FIX 3 — hc-dashboard.html: page was blank
//
// Root cause: the page div (#page) starts empty and is filled 100% by JS.
// If guardAuth or initShell fails/redirects before the skeleton injection,
// or if the module script throws silently, the user sees a blank white shell.
// Additionally the error path only replaced #stats, leaving #panels as raw
// skeleton HTML forever.
//
// Fix: restructure like admin-dashboard — put skeleton HTML directly in the
// page div so content is visible even before JS runs. Then JS only replaces
// #stats and #panels once the API resolves. The error path now clears both.
// ═════════════════════════════════════════════════════════════════

$hcNew = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Elite 2.0</title>
  <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<div class="shell">
  <div class="main">
    <div class="page">

      <div class="page-header">
        <div class="page-title" id="greeting">Dashboard</div>
        <div class="page-subtitle">Here&rsquo;s what&rsquo;s happening at your academy today.</div>
      </div>

      <div class="stat-grid" id="stats">
        <div class="stat-card"><div class="skeleton skeleton-text" style="width:60%"></div><div class="skeleton skeleton-title" style="margin-top:8px;width:40%"></div></div>
        <div class="stat-card"><div class="skeleton skeleton-text" style="width:60%"></div><div class="skeleton skeleton-title" style="margin-top:8px;width:40%"></div></div>
        <div class="stat-card"><div class="skeleton skeleton-text" style="width:60%"></div><div class="skeleton skeleton-title" style="margin-top:8px;width:40%"></div></div>
        <div class="stat-card"><div class="skeleton skeleton-text" style="width:60%"></div><div class="skeleton skeleton-title" style="margin-top:8px;width:40%"></div></div>
      </div>

      <div class="grid" style="grid-template-columns:1fr 1fr;gap:1.5rem" id="panels">
        <div class="card">
          <div class="card-title skeleton skeleton-text" style="width:50%"></div>
          <div class="skeleton skeleton-card" style="margin-top:1rem"></div>
        </div>
        <div class="card">
          <div class="card-title skeleton skeleton-text" style="width:50%"></div>
          <div class="skeleton skeleton-card" style="margin-top:1rem"></div>
        </div>
      </div>

    </div>
  </div>
</div>

<script type="module">
  import { guardAuth } from '../js/auth-guard.js';
  import { initShell } from '../js/shell.js';
  import api from '../js/api.js';
  import ui from '../js/ui.js';

  const user = guardAuth(['admin', 'head_coach']);
  await initShell({ title: 'Dashboard' });

  document.getElementById('greeting').textContent = `Good to see you, ${user.first_name}`;

  const { data, error } = await api.analytics.dashboard();

  if (error || !data) {
    document.getElementById('stats').innerHTML  = ui.empty('⚠', 'Could not load dashboard', error || 'API error');
    document.getElementById('panels').innerHTML = '';
    return;
  }

  const t = data.totals;
  document.getElementById('stats').innerHTML = `
    <div class="stat-card fade-up">
      <div class="stat-label">Total Students</div>
      <div class="stat-value text-accent">${t.students ?? '—'}</div>
    </div>
    <div class="stat-card fade-up">
      <div class="stat-label">Active Coaches</div>
      <div class="stat-value">${t.coaches ?? '—'}</div>
    </div>
    <div class="stat-card fade-up">
      <div class="stat-label">Avg Attendance</div>
      <div class="stat-value ${data.avg_attendance_30d >= 80 ? 'text-success' : 'text-warning'}">${data.avg_attendance_30d ?? '—'}%</div>
      <div class="stat-change">Last 30 days</div>
    </div>
    <div class="stat-card fade-up">
      <div class="stat-label">Pending Promotions</div>
      <div class="stat-value ${t.pending_promotions > 0 ? 'text-warning' : ''}">${t.pending_promotions ?? '—'}</div>
    </div>
  `;

  document.getElementById('panels').innerHTML = `
    <div class="card fade-up">
      <div class="card-header">
        <div>
          <div class="card-title">Promotion Pipeline</div>
          <div class="card-subtitle">Students approaching belt eligibility</div>
        </div>
        <a href="promotions.html" class="btn btn-secondary btn-sm">View all</a>
      </div>
      ${data.promotion_pipeline.length === 0
        ? ui.empty('🥋', 'No students yet', '')
        : data.promotion_pipeline.map(s => `
            <div class="flex items-center gap-3" style="padding:0.5rem 0;border-bottom:1px solid var(--color-border)">
              ${ui.avatar({ name: s.name })}
              <div class="flex-1 truncate">
                <div class="font-medium text-sm truncate">${s.name}</div>
                <div class="text-xs text-dimmed flex items-center gap-2">
                  ${ui.beltDot(s.color_hex, s.current_belt)} ${s.current_belt || 'No belt'}
                </div>
              </div>
              <a href="students.html?id=${s.id}" class="btn btn-ghost btn-sm">View</a>
            </div>`).join('')}
    </div>

    <div class="card fade-up">
      <div class="card-header">
        <div>
          <div class="card-title">Low Attendance</div>
          <div class="card-subtitle">Students below 70% in last 30 days</div>
        </div>
      </div>
      ${data.low_attendance.length === 0
        ? ui.empty('✓', 'Great attendance!', 'No students below 70%.')
        : data.low_attendance.map(s => `
            <div class="flex items-center gap-3" style="padding:0.5rem 0;border-bottom:1px solid var(--color-border)">
              ${ui.avatar({ name: s.name })}
              <div class="flex-1 truncate">
                <div class="font-medium text-sm truncate">${s.name}</div>
                ${ui.progressBar(s.pct)}
              </div>
              <span class="font-mono text-sm text-danger">${s.pct}%</span>
            </div>`).join('')}
    </div>
  `;
</script>
</body>
</html>
HTML;

overwrite(BASE . '/pages/hc-dashboard.html', $hcNew, 'hc-dashboard.html — rewrite with static skeleton + fixed error path');

// ─────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────
echo "\n=== patch4.php results ===\n\n";
foreach ($fixed as $msg)  echo "  ✔ $msg\n";
echo "\n";
foreach ($errors as $msg) echo "  ✘ $msg\n";

if ($errors) {
    echo "\n[FAIL] " . count($errors) . " error(s). Review above.\n\n";
    exit(1);
}
echo "\n[DONE] All " . count($fixed) . " patches applied.\n\n";
echo "Post-run checklist:\n";
echo "  1. Hard-refresh the login page (Ctrl+Shift+R) — verify 👁 toggle shows/hides password.\n";
echo "  2. Log in as head_coach — sidebar Sign-out button should be grey text (not blue/system default).\n";
echo "     Hover over it — it should turn red.\n";
echo "  3. Visit /elite2/pages/hc-dashboard.html — you should see the skeleton cards immediately,\n";
echo "     then real data once the API resolves (or a ⚠ error card if DB is empty — not a blank page).\n";
echo "  4. Test dashboard when not logged in — it should redirect to /login.html, not blank.\n";
