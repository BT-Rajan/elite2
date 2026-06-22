/**
 * Elite 2.0 — Auth Guard
 * Include as the first module script on every authenticated page.
 *
 * Usage:
 *   import { guardAuth } from '/js/auth-guard.js';
 *   guardAuth(['admin', 'head_coach']);   // allowed roles
 *
 * If no roles passed, any authenticated user is allowed.
 */

import { store } from './api.js';

/**
 * Redirect to login if not authenticated, or to 403 if wrong role.
 * @param {string[]} [roles]  Allowed roles. Empty = any role allowed.
 */
function guardAuth(roles = []) {
  const user  = store.user;
  const token = store.accessToken;

  if (!token || !user) {
    window.location.replace('/login.html');
    throw new Error('unauthenticated');
  }

  if (roles.length > 0 && !roles.includes(user.role)) {
    window.location.replace('/pages/403.html');
    throw new Error('forbidden');
  }

  return user;
}

export { guardAuth };
