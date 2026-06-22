<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Response;

final class StudentController extends Controller
{
    // GET /students
    public function index(): void
    {
        $role   = $this->userRole();
        $userId = $this->userId();
        $search = $this->query('search');
        $belt   = $this->query('belt_id');
        $disc   = $this->query('discipline_id');
        $page   = max(1, (int)$this->query('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $where = ["u.role='student'", "u.is_active=1"];
        $bind  = [];

        // Coaches only see assigned students
        if ($role === 'coach') {
            $where[] = 'cs.coach_id = ?';
            $bind[]  = $userId;
        }
        if ($search) {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $s = "%$search%"; $bind[] = $s; $bind[] = $s; $bind[] = $s;
        }
        if ($belt) { $where[] = 'sp.current_belt_id = ?'; $bind[] = (int)$belt; }
        if ($disc) { $where[] = 'sp.discipline_id = ?';   $bind[] = (int)$disc; }

        $w = implode(' AND ', $where);
        $join = $role === 'coach' ? 'JOIN coach_students cs ON cs.student_id=u.id' : '';

        $cnt = $this->db->prepare("SELECT COUNT(*) FROM users u $join LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE $w");
        $cnt->execute($bind);
        $total = (int)$cnt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.avatar, u.created_at,
                   sp.discipline_id, sp.current_belt_id, sp.dob, sp.enrolled_at,
                   b.name AS belt_name, b.color_hex AS belt_color,
                   d.name AS discipline_name
            FROM users u
            $join
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN belts b ON b.id = sp.current_belt_id
            LEFT JOIN disciplines d ON d.id = sp.discipline_id
            WHERE $w
            ORDER BY u.first_name, u.last_name
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($bind);
        $this->ok(['students' => $stmt->fetchAll(), 'total' => $total, 'page' => $page]);
    }

    // GET /students/{id}
    public function show(array $p): void
    {
        $id = (int)$p['id'];
        $this->assertAccess($id);

        $stmt = $this->db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.avatar, u.created_at,
                   sp.discipline_id, sp.current_belt_id, sp.dob, sp.gender,
                   sp.emergency_name, sp.emergency_phone, sp.notes, sp.enrolled_at,
                   b.name AS belt_name, b.color_hex AS belt_color,
                   d.name AS discipline_name
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN belts b ON b.id = sp.current_belt_id
            LEFT JOIN disciplines d ON d.id = sp.discipline_id
            WHERE u.id = ? AND u.role = 'student' AND u.is_active = 1
        ");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        if (!$student) Response::notFound('Student not found.');

        // Points total
        $pts = $this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM points WHERE student_id=?');
        $pts->execute([$id]);
        $student['total_points'] = (int)$pts->fetchColumn();

        // Achievements
        $ach = $this->db->prepare('SELECT a.key_name,a.label,a.icon,sa.earned_at FROM student_achievements sa JOIN achievements a ON a.id=sa.achievement_id WHERE sa.student_id=?');
        $ach->execute([$id]);
        $student['achievements'] = $ach->fetchAll();

        $this->ok(['student' => $student]);
    }

    // PATCH /students/{id}
    public function update(array $p): void
    {
        $id = (int)$p['id'];
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();

        // User fields
        $userFields = ['first_name','last_name','phone','avatar'];
        $sets = []; $vals = [];
        foreach ($userFields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "$f=?"; $vals[] = $b[$f]; }
        }
        if ($sets) {
            $vals[] = $id;
            $this->db->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?')->execute($vals);
        }

        // Profile fields
        $profFields = ['discipline_id','current_belt_id','dob','gender','emergency_name','emergency_phone','notes'];
        $psets = []; $pvals = [];
        foreach ($profFields as $f) {
            if (array_key_exists($f, $b)) { $psets[] = "$f=?"; $pvals[] = $b[$f]; }
        }
        if ($psets) {
            $pvals[] = $id;
            $this->db->prepare('UPDATE student_profiles SET '.implode(',',$psets).' WHERE user_id=?')->execute($pvals);
        }
        $this->ok();
    }

    // DELETE /students/{id} — soft
    public function delete(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $this->db->prepare('UPDATE users SET is_active=0 WHERE id=? AND role="student"')->execute([(int)$p['id']]);
        $this->ok();
    }

    // ── Attendance ─────────────────────────────────────────────────────────────

    // GET /students/{id}/attendance
    public function attendanceHistory(array $p): void
    {
        $id = (int)$p['id'];
        $this->assertAccess($id);
        $from = $this->query('from', date('Y-m-01'));
        $to   = $this->query('to',   date('Y-m-t'));

        $stmt = $this->db->prepare("
            SELECT att.status, att.marked_at, ass.session_date,
                   c.title AS class_title, d.name AS discipline
            FROM attendance att
            JOIN attendance_sessions ass ON ass.id = att.session_id
            JOIN classes c ON c.id = ass.class_id
            JOIN disciplines d ON d.id = c.discipline_id
            WHERE att.student_id = ? AND ass.session_date BETWEEN ? AND ?
            ORDER BY ass.session_date DESC
        ");
        $stmt->execute([$id, $from, $to]);
        $records = $stmt->fetchAll();

        $total   = count($records);
        $present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
        $late    = count(array_filter($records, fn($r) => $r['status'] === 'late'));
        $pct     = $total > 0 ? round(($present + $late * 0.5) / $total * 100, 1) : 0;

        $this->ok([
            'records' => $records,
            'summary' => ['total' => $total, 'present' => $present, 'late' => $late, 'absent' => $total - $present - $late, 'percentage' => $pct],
        ]);
    }

    // ── Belt eligibility ───────────────────────────────────────────────────────

    // GET /students/{id}/belt-eligibility
    public function beltEligibility(array $p): void
    {
        $id = (int)$p['id'];
        $this->assertAccess($id);

        $profile = $this->db->prepare('SELECT sp.*, b.discipline_id FROM student_profiles sp LEFT JOIN belts b ON b.id=sp.current_belt_id WHERE sp.user_id=?');
        $profile->execute([$id]);
        $sp = $profile->fetch();
        if (!$sp) Response::notFound('Student profile not found.');

        // Find next belt
        $nextBelt = $this->db->prepare("
            SELECT * FROM belts
            WHERE discipline_id=? AND sort_order > COALESCE(
                (SELECT sort_order FROM belts WHERE id=?), -1
            )
            ORDER BY sort_order LIMIT 1
        ");
        $nextBelt->execute([$sp['discipline_id'], $sp['current_belt_id']]);
        $next = $nextBelt->fetch();
        if (!$next) { $this->ok(['eligible' => false, 'reason' => 'Already at highest belt.', 'next_belt' => null]); }

        // Attendance check (last 90 days)
        $attStmt = $this->db->prepare("
            SELECT COUNT(*) total,
                   SUM(CASE WHEN att.status='present' THEN 1 WHEN att.status='late' THEN 0.5 ELSE 0 END) credits
            FROM attendance att
            JOIN attendance_sessions ass ON ass.id=att.session_id
            WHERE att.student_id=? AND ass.session_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ");
        $attStmt->execute([$id]);
        $attData = $attStmt->fetch();
        $attPct  = $attData['total'] > 0 ? round($attData['credits'] / $attData['total'] * 100, 1) : 0;

        // Avg eval score (last 3)
        $evalStmt = $this->db->prepare('SELECT ROUND(AVG(overall),1) avg FROM evaluations WHERE student_id=? ORDER BY eval_date DESC LIMIT 3');
        $evalStmt->execute([$id]);
        $avgScore = (float)$evalStmt->fetchColumn();

        // Skills
        $skills = $this->db->prepare("
            SELECT bs.id, bs.name,
                   COALESCE(ss.level,'not_started') AS level
            FROM belt_skills bs
            LEFT JOIN student_skills ss ON ss.skill_id=bs.id AND ss.student_id=?
            WHERE bs.belt_id=?
            ORDER BY bs.sort_order
        ");
        $skills->execute([$id, $next['id']]);
        $skillRows = $skills->fetchAll();
        $completedSkills = count(array_filter($skillRows, fn($s) => in_array($s['level'], ['competent','advanced'], true)));
        $totalSkills = count($skillRows);

        $checks = [
            'attendance' => ['pass' => $attPct >= $next['min_attendance_pct'], 'value' => $attPct, 'required' => $next['min_attendance_pct']],
            'eval_score' => ['pass' => $avgScore >= $next['min_eval_score'],   'value' => $avgScore,'required' => $next['min_eval_score']],
            'skills'     => ['pass' => $totalSkills === 0 || $completedSkills === $totalSkills, 'completed' => $completedSkills, 'total' => $totalSkills],
            'classes'    => ['pass' => (int)($attData['total'] ?? 0) >= $next['min_classes'], 'value' => (int)($attData['total'] ?? 0), 'required' => $next['min_classes']],
        ];

        $eligible = array_reduce($checks, fn($carry, $c) => $carry && $c['pass'], true);

        $this->ok([
            'eligible'   => $eligible,
            'next_belt'  => $next,
            'checks'     => $checks,
            'skills'     => $skillRows,
        ]);
    }

    // ── Points & Achievements ──────────────────────────────────────────────────

    // GET /students/{id}/points
    public function points(array $p): void
    {
        $id = (int)$p['id'];
        $this->assertAccess($id);
        $stmt = $this->db->prepare('SELECT * FROM points WHERE student_id=? ORDER BY awarded_at DESC LIMIT 100');
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll();
        $total = array_sum(array_column($rows, 'amount'));
        $this->ok(['points' => $rows, 'total' => $total]);
    }

    // POST /students/{id}/points — award points manually
    public function awardPoints(array $p): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        $this->require($b, ['amount', 'reason']);
        $this->db->prepare('INSERT INTO points (student_id,amount,reason,note) VALUES (?,?,?,?)')->execute([
            (int)$p['id'], (int)$b['amount'], $b['reason'], $b['note'] ?? null
        ]);
        $this->created();
    }

    // ── Coach notes ────────────────────────────────────────────────────────────

    // GET /students/{id}/notes
    public function notes(array $p): void
    {
        $id = (int)$p['id'];
        $this->assertAccess($id);
        $stmt = $this->db->prepare("
            SELECT cn.*, CONCAT(u.first_name,' ',u.last_name) AS coach_name
            FROM coach_notes cn JOIN users u ON u.id=cn.coach_id
            WHERE cn.student_id=? ORDER BY cn.created_at DESC
        ");
        $stmt->execute([$id]);
        $this->ok(['notes' => $stmt->fetchAll()]);
    }

    // POST /students/{id}/notes
    public function addNote(array $p): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        $this->require($b, ['body']);
        $this->db->prepare('INSERT INTO coach_notes (coach_id,student_id,body) VALUES (?,?,?)')->execute([
            $this->userId(), (int)$p['id'], trim($b['body'])
        ]);
        $this->created();
    }

    // ── Private ────────────────────────────────────────────────────────────────

    /** Students can only access their own data; coaches only see assigned students. */
    private function assertAccess(int $studentId): void
    {
        $role = $this->userRole();
        if ($role === 'admin' || $role === 'head_coach') return;
        if ($role === 'student' && $this->userId() === $studentId) return;
        if ($role === 'coach') {
            $s = $this->db->prepare('SELECT 1 FROM coach_students WHERE coach_id=? AND student_id=? LIMIT 1');
            $s->execute([$this->userId(), $studentId]);
            if ($s->fetchColumn()) return;
        }
        Response::forbidden();
    }
}
