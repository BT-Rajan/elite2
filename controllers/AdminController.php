<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Response;

final class AdminController extends Controller
{
    // ── Users ──────────────────────────────────────────────────────────────────

    // GET /admin/users
    public function listUsers(): void
    {
        $this->requireRole('admin');
        $role   = $this->query('role');
        $search = $this->query('search');
        $page   = max(1, (int)$this->query('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $where = ['1=1']; $bind = [];
        if ($role)   { $where[] = 'role = ?';                      $bind[] = $role; }
        if ($search) { $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
                       $s = "%$search%"; $bind[] = $s; $bind[] = $s; $bind[] = $s; }

        $w = implode(' AND ', $where);
        $total = (int)$this->db->prepare("SELECT COUNT(*) FROM users WHERE $w")->execute($bind) && 0;
        $cnt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE $w");
        $cnt->execute($bind);
        $total = (int)$cnt->fetchColumn();

        $stmt = $this->db->prepare("SELECT id,email,role,first_name,last_name,phone,is_active,created_at FROM users WHERE $w ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($bind);

        $this->ok(['users' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    // GET /admin/users/{id}
    public function getUser(array $p): void
    {
        $this->requireRole('admin');
        $user = $this->findById('users', (int)$p['id']);
        if (!$user) Response::notFound('User not found.');
        unset($user['password']);
        $this->ok(['user' => $user]);
    }

    // PATCH /admin/users/{id}
    public function updateUser(array $p): void
    {
        $this->requireRole('admin');
        $b = $this->body();
        $allowed = ['first_name','last_name','phone','role','is_active'];
        $sets = []; $vals = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "$f = ?"; $vals[] = $b[$f]; }
        }
        if (!$sets) Response::error('Nothing to update.', 422);
        $vals[] = (int)$p['id'];
        $this->db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        $this->ok();
    }

    // DELETE /admin/users/{id} — soft delete
    public function deleteUser(array $p): void
    {
        $this->requireRole('admin');
        $id = (int)$p['id'];
        if ($id === $this->userId()) Response::error('Cannot delete your own account.', 400);
        $this->db->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$id]);
        $this->ok();
    }

    // ── Settings ───────────────────────────────────────────────────────────────

    // GET /admin/settings
    public function getSettings(): void
    {
        $this->requireRole('admin');
        $rows = $this->db->query('SELECT key_name, value FROM settings ORDER BY key_name')->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['key_name']] = $r['value'];
        $this->ok(['settings' => $map]);
    }

    // PUT /admin/settings
    public function updateSettings(): void
    {
        $this->requireRole('admin');
        $b = $this->body();
        if (!is_array($b) || empty($b)) Response::unprocessable('No settings provided.');

        $stmt = $this->db->prepare('INSERT INTO settings (key_name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
        foreach ($b as $k => $v) {
            if (preg_match('/^[a-z_]{1,80}$/', (string)$k)) {
                $stmt->execute([(string)$k, (string)$v]);
            }
        }
        $this->ok();
    }

    // ── Coaches ────────────────────────────────────────────────────────────────

    // GET /admin/coaches
    public function listCoaches(): void
    {
        $this->requireRole('admin', 'head_coach');
        $stmt = $this->db->query("SELECT id,first_name,last_name,email,phone,is_active,created_at FROM users WHERE role='coach' ORDER BY first_name");
        $this->ok(['coaches' => $stmt->fetchAll()]);
    }

    // GET /admin/coaches/{id}/students
    public function coachStudents(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $stmt = $this->db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email,
                   b.name AS belt, d.name AS discipline
            FROM coach_students cs
            JOIN users u ON u.id = cs.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN belts b ON b.id = sp.current_belt_id
            LEFT JOIN disciplines d ON d.id = sp.discipline_id
            WHERE cs.coach_id = ? AND u.is_active = 1
            ORDER BY u.first_name
        ");
        $stmt->execute([(int)$p['id']]);
        $this->ok(['students' => $stmt->fetchAll()]);
    }

    // POST /admin/coaches/{id}/students — assign student to coach
    public function assignStudent(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['student_id']);
        try {
            $this->db->prepare('INSERT IGNORE INTO coach_students (coach_id,student_id) VALUES (?,?)')->execute([(int)$p['id'], (int)$b['student_id']]);
        } catch (\Throwable) {}
        $this->ok();
    }

    // DELETE /admin/coaches/{id}/students/{studentId}
    public function unassignStudent(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $this->db->prepare('DELETE FROM coach_students WHERE coach_id=? AND student_id=?')->execute([(int)$p['id'], (int)$p['studentId']]);
        $this->ok();
    }

    // ── Disciplines ────────────────────────────────────────────────────────────

    // GET /admin/disciplines
    public function listDisciplines(): void
    {
        $this->requireRole('admin', 'head_coach');
        $stmt = $this->db->query('SELECT * FROM disciplines WHERE is_active=1 ORDER BY sort_order, name');
        $this->ok(['disciplines' => $stmt->fetchAll()]);
    }

    // POST /admin/disciplines
    public function createDiscipline(): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['name']);
        $this->db->prepare('INSERT INTO disciplines (name,description,sort_order) VALUES (?,?,?)')->execute([trim($b['name']), $b['description'] ?? null, (int)($b['sort_order'] ?? 0)]);
        $this->created(['id' => (int)$this->db->lastInsertId()]);
    }

    // PATCH /admin/disciplines/{id}
    public function updateDiscipline(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->db->prepare('UPDATE disciplines SET name=COALESCE(?,name), description=COALESCE(?,description), sort_order=COALESCE(?,sort_order) WHERE id=?')
            ->execute([$b['name'] ?? null, $b['description'] ?? null, $b['sort_order'] ?? null, (int)$p['id']]);
        $this->ok();
    }

    // ── Belts ──────────────────────────────────────────────────────────────────

    // GET /admin/disciplines/{id}/belts
    public function listBelts(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $stmt = $this->db->prepare('SELECT * FROM belts WHERE discipline_id=? ORDER BY sort_order');
        $stmt->execute([(int)$p['id']]);
        $this->ok(['belts' => $stmt->fetchAll()]);
    }

    // POST /admin/disciplines/{id}/belts
    public function createBelt(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['name']);
        $this->db->prepare("
            INSERT INTO belts (discipline_id,name,color_hex,sort_order,min_attendance_pct,min_eval_score,min_classes)
            VALUES (?,?,?,?,?,?,?)
        ")->execute([
            (int)$p['id'], trim($b['name']), $b['color_hex'] ?? '#FFFFFF',
            (int)($b['sort_order'] ?? 0),
            (int)($b['min_attendance_pct'] ?? 80),
            (int)($b['min_eval_score'] ?? 7),
            (int)($b['min_classes'] ?? 0),
        ]);
        $this->created(['id' => (int)$this->db->lastInsertId()]);
    }

    // PATCH /admin/belts/{id}
    public function updateBelt(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $allowed = ['name','color_hex','sort_order','min_attendance_pct','min_eval_score','min_classes'];
        $sets = []; $vals = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "$f=?"; $vals[] = $b[$f]; }
        }
        if ($sets) {
            $vals[] = (int)$p['id'];
            $this->db->prepare('UPDATE belts SET '.implode(',',$sets).' WHERE id=?')->execute($vals);
        }
        $this->ok();
    }

    // GET /admin/belts/{id}/skills
    public function listBeltSkills(array $p): void
    {
        $stmt = $this->db->prepare('SELECT * FROM belt_skills WHERE belt_id=? ORDER BY sort_order');
        $stmt->execute([(int)$p['id']]);
        $this->ok(['skills' => $stmt->fetchAll()]);
    }

    // POST /admin/belts/{id}/skills
    public function createBeltSkill(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['name']);
        $this->db->prepare('INSERT INTO belt_skills (belt_id,name,description,sort_order) VALUES (?,?,?,?)')->execute([(int)$p['id'], trim($b['name']), $b['description'] ?? null, (int)($b['sort_order'] ?? 0)]);
        $this->created(['id' => (int)$this->db->lastInsertId()]);
    }

    // DELETE /admin/belt-skills/{id}
    public function deleteBeltSkill(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $this->db->prepare('DELETE FROM belt_skills WHERE id=?')->execute([(int)$p['id']]);
        $this->ok();
    }
}
