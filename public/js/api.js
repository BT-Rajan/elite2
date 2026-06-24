/**
 * Elite 2.0 — API Client
 * Single point of contact for all API calls.
 * Handles auth headers, token refresh, and error normalisation.
 */

// Derive base paths from this script's URL so the app works at any sub-path
// (e.g. http://localhost/elite2/ or http://localhost/)
const _publicRoot = (() => {
  try {
    return new URL(import.meta.url).pathname.replace(/\/js\/api\.js$/, '');
  } catch { return ''; }
})();
const API_BASE   = _publicRoot + '/api';
const LOGIN_PAGE = _publicRoot + '/login.html';

const store = {
  get accessToken()  { return localStorage.getItem('e2_access'); },
  set accessToken(v) { v ? localStorage.setItem('e2_access', v) : localStorage.removeItem('e2_access'); },
  get refreshToken() { return localStorage.getItem('e2_refresh'); },
  set refreshToken(v){ v ? localStorage.setItem('e2_refresh', v) : localStorage.removeItem('e2_refresh'); },
  get user()         { try { return JSON.parse(localStorage.getItem('e2_user') || 'null'); } catch { return null; } },
  set user(v)        { v ? localStorage.setItem('e2_user', JSON.stringify(v)) : localStorage.removeItem('e2_user'); },
  clear() {
    localStorage.removeItem('e2_access');
    localStorage.removeItem('e2_refresh');
    localStorage.removeItem('e2_user');
  },
};

let _refreshPromise = null;

async function _refreshAccessToken() {
  if (_refreshPromise) return _refreshPromise;

  _refreshPromise = (async () => {
    const refresh = store.refreshToken;
    if (!refresh) throw new Error('No refresh token');

    const res = await fetch(`${API_BASE}/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refresh }),
    });

    if (!res.ok) {
      store.clear();
      window.location.href = LOGIN_PAGE;
      throw new Error('Session expired');
    }

    const data = await res.json();
    store.accessToken = data.access_token;
    if (data.refresh_token) store.refreshToken = data.refresh_token;
    return data.access_token;
  })();

  _refreshPromise.finally(() => { _refreshPromise = null; });
  return _refreshPromise;
}

/**
 * Core fetch wrapper.
 * @param {string} path  — API path, e.g. '/students'
 * @param {object} opts  — fetch options (method, body, etc.)
 * @param {boolean} retry — internal: whether this is a retry after token refresh
 */
async function apiFetch(path, opts = {}, retry = false) {
  const token = store.accessToken;

  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(opts.headers || {}),
  };

  const res = await fetch(`${API_BASE}${path}`, {
    ...opts,
    headers,
    body: opts.body ? (typeof opts.body === 'string' ? opts.body : JSON.stringify(opts.body)) : undefined,
  });

  // Token expired — try refresh once
  if (res.status === 401 && !retry) {
    try {
      await _refreshAccessToken();
      return apiFetch(path, opts, true);
    } catch {
      return res;
    }
  }

  return res;
}

/** Parse response — always returns { data, error, status } */
async function apiCall(path, opts = {}) {
  try {
    const res = await apiFetch(path, opts);
    const json = await res.json().catch(() => ({}));

    if (!res.ok) {
      return { data: null, error: json.error || `HTTP ${res.status}`, status: res.status };
    }
    return { data: json, error: null, status: res.status };
  } catch (err) {
    return { data: null, error: err.message || 'Network error', status: 0 };
  }
}

// ── Convenience methods ────────────────────────────────────────────────────────

const api = {
  get:    (path, params) => {
    const qs = params ? '?' + new URLSearchParams(params).toString() : '';
    return apiCall(path + qs, { method: 'GET' });
  },
  post:   (path, body)   => apiCall(path, { method: 'POST',   body }),
  patch:  (path, body)   => apiCall(path, { method: 'PATCH',  body }),
  put:    (path, body)   => apiCall(path, { method: 'PUT',    body }),
  delete: (path)         => apiCall(path, { method: 'DELETE' }),

  // ── Auth convenience ─────────────────────────────────────────────────────────
  auth: {
    login:          (email, password)      => api.post('/auth/login', { email, password }),
    logout:         ()                     => api.post('/auth/logout', { refresh_token: store.refreshToken }),
    me:             ()                     => api.get('/auth/me'),
    changePassword: (current, next)        => api.post('/auth/change-password', { current_password: current, new_password: next }),
    forgotPassword: (email)                => api.post('/auth/forgot-password', { email }),
    resetPassword:  (token, password)      => api.post('/auth/reset-password', { token, new_password: password }),
    register:       (data)                 => api.post('/auth/register', data),
    updateProfile:  (data)                 => api.patch('/auth/me', data),
  },

  students: {
    list:           (params)               => api.get('/students', params),
    get:            (id)                   => api.get(`/students/${id}`),
    update:         (id, data)             => api.patch(`/students/${id}`, data),
    delete:         (id)                   => api.delete(`/students/${id}`),
    attendance:     (id, params)           => api.get(`/students/${id}/attendance`, params),
    eligibility:    (id)                   => api.get(`/students/${id}/belt-eligibility`),
    points:         (id)                   => api.get(`/students/${id}/points`),
    awardPoints:    (id, data)             => api.post(`/students/${id}/points`, data),
    notes:          (id)                   => api.get(`/students/${id}/notes`),
    addNote:        (id, body)             => api.post(`/students/${id}/notes`, { body }),
    skills:         (id)                   => api.get(`/students/${id}/skills`),
    updateSkill:    (id, skillId, level)   => api.put(`/students/${id}/skills/${skillId}`, { level }),
  },

  classes: {
    list:           (params)               => api.get('/classes', params),
    create:         (data)                 => api.post('/classes', data),
    update:         (id, data)             => api.patch(`/classes/${id}`, data),
  },

  attendance: {
    sessions:       (params)               => api.get('/attendance/sessions', params),
    open:           (classId, date)        => api.post('/attendance/sessions', { class_id: classId, session_date: date }),
    close:          (id)                   => api.post(`/attendance/sessions/${id}/close`),
    records:        (id)                   => api.get(`/attendance/sessions/${id}/records`),
    mark:           (id, data)             => api.post(`/attendance/sessions/${id}/mark`, data),
    bulkMark:       (id, records)          => api.post(`/attendance/sessions/${id}/bulk-mark`, { records }),
  },

  evaluations: {
    list:           (params)               => api.get('/evaluations', params),
    get:            (id)                   => api.get(`/evaluations/${id}`),
    create:         (data)                 => api.post('/evaluations', data),
  },

  promotions: {
    list:           (params)               => api.get('/promotions', params),
    create:         (data)                 => api.post('/promotions', data),
  },

  seminars: {
    list:           ()                     => api.get('/seminars'),
    create:         (data)                 => api.post('/seminars', data),
    markAttend:     (id, studentId)        => api.post(`/seminars/${id}/attend`, { student_id: studentId }),
  },

  analytics: {
    dashboard:      ()                     => api.get('/analytics/dashboard'),
    coachReport:    (params)               => api.get('/analytics/coach-report', params),
  },

  admin: {
    users:          (params)               => api.get('/admin/users', params),
    getUser:        (id)                   => api.get(`/admin/users/${id}`),
    updateUser:     (id, data)             => api.patch(`/admin/users/${id}`, data),
    deleteUser:     (id)                   => api.delete(`/admin/users/${id}`),
    settings:       ()                     => api.get('/admin/settings'),
    saveSettings:   (data)                 => api.put('/admin/settings', data),
    coaches:        ()                     => api.get('/admin/coaches'),
    coachStudents:  (id)                   => api.get(`/admin/coaches/${id}/students`),
    assignStudent:  (coachId, studentId)   => api.post(`/admin/coaches/${coachId}/students`, { student_id: studentId }),
    unassignStudent:(coachId, studentId)   => api.delete(`/admin/coaches/${coachId}/students/${studentId}`),
    disciplines:    ()                     => api.get('/admin/disciplines'),
    createDisc:     (data)                 => api.post('/admin/disciplines', data),
    belts:          (discId)               => api.get(`/admin/disciplines/${discId}/belts`),
    createBelt:     (discId, data)         => api.post(`/admin/disciplines/${discId}/belts`, data),
    beltSkills:     (beltId)              => api.get(`/admin/belts/${beltId}/skills`),
    createSkill:    (beltId, data)         => api.post(`/admin/belts/${beltId}/skills`, data),
    deleteSkill:    (id)                   => api.delete(`/admin/belt-skills/${id}`),
  },

  notifications: {
    list:           (params)               => api.get('/notifications', params),
    markRead:       (id)                   => api.patch(`/notifications/${id}/read`),
    markAllRead:    ()                     => api.post('/notifications/read-all'),
    delete:         (id)                   => api.delete(`/notifications/${id}`),
    broadcast:      (data)                 => api.post('/notifications/broadcast', data),
  },

  store, // expose auth store for login/logout flow
};

export default api;
export { store };
