<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Response;

final class AuthController extends Controller
{
    // POST /auth/login
    public function login(): void
    {
        $b = $this->body();
        $this->require($b, ['email', 'password']);

        $email = strtolower(trim($b['email']));

        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Constant-time comparison — prevents user enumeration via timing
        $hash = $user['password'] ?? '$2y$12$usqxhRkz4o0gpKpHCp5G0OG2cYR1zHcAOzKnL6r6/5eY8K3eThjQK';
        if (!$user || !Auth::verifyPassword($b['password'], $hash)) {
            Response::error('Invalid credentials.', 401);
        }

        [$access, $refresh] = $this->mintTokens($user);
        $this->ok(['access_token' => $access, 'refresh_token' => $refresh, 'user' => $this->publicUser($user)]);
    }

    // POST /auth/logout
    public function logout(): void
    {
        $b = $this->body();
        if (!empty($b['refresh_token'])) {
            $hash = hash('sha256', $b['refresh_token']);
            $this->db->prepare('UPDATE refresh_tokens SET revoked=1 WHERE token_hash=?')->execute([$hash]);
        }
        $this->ok();
    }

    // POST /auth/refresh
    public function refresh(): void
    {
        $b = $this->body();
        if (empty($b['refresh_token'])) Response::error('refresh_token required.', 422);

        $payload = Auth::verifyRefreshToken($b['refresh_token']);
        if (!$payload) Response::unauthorized('Invalid or expired refresh token.');

        $hash = hash('sha256', $b['refresh_token']);
        $row = $this->db->prepare('SELECT * FROM refresh_tokens WHERE token_hash=? AND revoked=0 AND expires_at > NOW() LIMIT 1');
        $row->execute([$hash]);
        if (!$row->fetch()) Response::unauthorized('Refresh token revoked or expired.');

        $user = $this->findById('users', (int)$payload->sub);
        if (!$user || !$user['is_active']) Response::unauthorized('Account not found or inactive.');

        // Rotate: revoke old, issue new
        $this->db->prepare('UPDATE refresh_tokens SET revoked=1 WHERE token_hash=?')->execute([$hash]);
        [$access, $refresh] = $this->mintTokens($user);
        $this->ok(['access_token' => $access, 'refresh_token' => $refresh]);
    }

    // GET /auth/me
    public function me(): void
    {
        $user = $this->findById('users', $this->userId());
        if (!$user) Response::notFound('User not found.');
        $this->ok(['user' => $this->publicUser($user)]);
    }

    // PATCH /auth/me
    public function updateMe(): void
    {
        $b = $this->body();
        $allowed = ['first_name', 'last_name', 'phone'];
        $sets = []; $vals = [];
        foreach ($allowed as $f) {
            if (isset($b[$f])) { $sets[] = "$f = ?"; $vals[] = trim($b[$f]); }
        }
        if (!$sets) Response::error('Nothing to update.', 422);
        $vals[] = $this->userId();
        $this->db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        $this->ok();
    }

    // POST /auth/change-password
    public function changePassword(): void
    {
        $b = $this->body();
        $this->require($b, ['current_password', 'new_password']);
        if (strlen($b['new_password']) < 8) Response::unprocessable('Password must be at least 8 characters.');

        $user = $this->findById('users', $this->userId());
        if (!Auth::verifyPassword($b['current_password'], $user['password'])) {
            Response::error('Current password is incorrect.', 401);
        }

        $this->db->prepare('UPDATE users SET password=? WHERE id=?')->execute([
            Auth::hashPassword($b['new_password']), $this->userId()
        ]);
        $this->ok();
    }

    // POST /auth/forgot-password
    public function forgotPassword(): void
    {
        $b = $this->body();
        $this->require($b, ['email']);
        $email = strtolower(trim($b['email']));

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email=? AND is_active=1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always return ok — don't leak whether email exists
        if (!$user) { $this->ok(['message' => 'If that email exists, a reset link was sent.']); }

        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', time() + 3600);

        $this->db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$user['id']]);
        $this->db->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,?)')->execute([$user['id'],$hash,$expiry]);

        // TODO: send email with reset link: APP_URL/reset-password?token=$token
        $this->ok(['message' => 'If that email exists, a reset link was sent.', '_debug_token' => APP_DEBUG ? $token : null]);
    }

    // POST /auth/reset-password
    public function resetPassword(): void
    {
        $b = $this->body();
        $this->require($b, ['token', 'new_password']);
        if (strlen($b['new_password']) < 8) Response::unprocessable('Password must be at least 8 characters.');

        $hash = hash('sha256', $b['token']);
        $stmt = $this->db->prepare('SELECT * FROM password_resets WHERE token_hash=? AND used=0 AND expires_at>NOW() LIMIT 1');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) Response::error('Invalid or expired token.', 400);

        $this->db->prepare('UPDATE users SET password=? WHERE id=?')->execute([Auth::hashPassword($b['new_password']), $row['user_id']]);
        $this->db->prepare('UPDATE password_resets SET used=1 WHERE id=?')->execute([$row['id']]);
        $this->db->prepare('UPDATE refresh_tokens SET revoked=1 WHERE user_id=?')->execute([$row['user_id']]);
        $this->ok();
    }

    // POST /auth/register (admin/head_coach creates users)
    public function register(): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['email', 'password', 'first_name', 'last_name', 'role']);

        $allowedRoles = match($this->userRole()) {
            'admin'      => ['admin', 'head_coach', 'coach', 'student'],
            'head_coach' => ['coach', 'student'],
            default      => [],
        };
        if (!in_array($b['role'], $allowedRoles, true)) Response::forbidden('Cannot create user with that role.');

        $email = strtolower(trim($b['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Response::unprocessable('Invalid email address.');
        if (strlen($b['password']) < 8) Response::unprocessable('Password must be at least 8 characters.');

        $ck = $this->db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $ck->execute([$email]);
        if ($ck->fetch()) Response::error('Email already registered.', 409);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                INSERT INTO users (email,password,role,first_name,last_name,phone,is_active)
                VALUES (?,?,?,?,?,?,1)
            ")->execute([
                $email, Auth::hashPassword($b['password']),
                $b['role'], trim($b['first_name']), trim($b['last_name']),
                $b['phone'] ?? null,
            ]);
            $userId = (int) $this->db->lastInsertId();

            if ($b['role'] === 'student') {
                $this->db->prepare("
                    INSERT INTO student_profiles (user_id,discipline_id,enrolled_at)
                    VALUES (?,?,CURDATE())
                ")->execute([$userId, $b['discipline_id'] ?? null]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->created(['id' => $userId, 'message' => 'User created.']);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function mintTokens(array $user): array
    {
        $access  = Auth::issueAccessToken($user);
        $refresh = Auth::issueRefreshToken($user);

        $hash = hash('sha256', $refresh);
        $exp  = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY);
        $this->db->prepare('DELETE FROM refresh_tokens WHERE user_id=? AND revoked=0 AND expires_at<NOW()')->execute([$user['id']]);
        $this->db->prepare('INSERT INTO refresh_tokens (user_id,token_hash,expires_at) VALUES (?,?,?)')->execute([$user['id'], $hash, $exp]);

        return [$access, $refresh];
    }

    private function publicUser(array $u): array
    {
        return [
            'id'         => (int) $u['id'],
            'email'      => $u['email'],
            'role'       => $u['role'],
            'first_name' => $u['first_name'],
            'last_name'  => $u['last_name'],
            'phone'      => $u['phone'],
            'avatar'     => $u['avatar'],
        ];
    }
}
