<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Response;

final class CoachController extends Controller
{
    // ── Classes ────────────────────────────────────────────────────────────────

    // GET /classes
    public function listClasses(): void
    {
        $role = $this->userRole();
        $where = ['c.is_active=1'];
        $bind  = [];
        if ($role === 'coach') { $where[] = 'c.coach_id=?'; $bind[] = $this->userId(); }

        $w = implode(' AND ', $where);
        $stmt = $this->db->prepare("
            SELECT c.*, d.name AS discipline_name,
                   CONCAT(u.first_name,' ',u.last_name) AS coach_name
            FROM classes c
            JOIN disciplines d ON d.id=c.discipline_id
            JOIN users u ON u.id=c.coach_id
            WHERE $w ORDER BY c.day_of_week, c.start_time
        ");
        $stmt->execute($bind);
        $this->ok(['classes' => $stmt->fetchAll()]);
    }

    // POST /classes
    public function createClass(): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['discipline_id','coach_id','title','day_of_week','start_time','end_time']);
        $this->db->prepare("
            INSERT INTO classes (discipline_id,coach_id,title,day_of_week,start_time,end_time,location)
            VALUES (?,?,?,?,?,?,?)
        ")->execute([$b['discipline_id'],$b['coach_id'],$b['title'],(int)$b['day_of_week'],$b['start_time'],$b['end_time'],$b['location']??null]);
        $this->created(['id' => (int)$this->db->lastInsertId()]);
    }

    // PATCH /classes/{id}
    public function updateClass(array $p): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $allowed = ['title','day_of_week','start_time','end_time','location','is_active','coach_id'];
        $sets=[]; $vals=[];
        foreach($allowed as $f) { if(array_key_exists($f,$b)){$sets[]="$f=?";$vals[]=$b[$f];} }
        if($sets){$vals[]=(int)$p['id'];$this->db->prepare('UPDATE classes SET '.implode(',',$sets).' WHERE id=?')->execute($vals);}
        $this->ok();
    }

    // ── Attendance sessions ────────────────────────────────────────────────────

    // GET /attendance/sessions
    public function listSessions(): void
    {
        $date  = $this->query('date', date('Y-m-d'));
        $where = ['ass.session_date=?'];
        $bind  = [$date];
        if ($this->userRole() === 'coach') { $where[] = 'ass.coach_id=?'; $bind[] = $this->userId(); }

        $stmt = $this->db->prepare("
            SELECT ass.*, c.title AS class_title, d.name AS discipline_name,
                   CONCAT(u.first_name,' ',u.last_name) AS coach_name,
                   COUNT(att.id) AS records_count
            FROM attendance_sessions ass
            JOIN classes c ON c.id=ass.class_id
            JOIN disciplines d ON d.id=c.discipline_id
            JOIN users u ON u.id=ass.coach_id
            LEFT JOIN attendance att ON att.session_id=ass.id
            WHERE ".implode(' AND ',$where)."
            GROUP BY ass.id ORDER BY ass.opened_at DESC
        ");
        $stmt->execute($bind);
        $this->ok(['sessions' => $stmt->fetchAll()]);
    }

    // POST /attendance/sessions
    public function openSession(): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        $this->require($b, ['class_id']);
        $date = $b['session_date'] ?? date('Y-m-d');

        $exists = $this->db->prepare('SELECT id FROM attendance_sessions WHERE class_id=? AND session_date=? AND closed_at IS NULL LIMIT 1');
        $exists->execute([$b['class_id'], $date]);
        if ($exists->fetch()) Response::error('A session is already open for this class today.', 409);

        $this->db->prepare('INSERT INTO attendance_sessions (class_id,coach_id,session_date) VALUES (?,?,?)')->execute([$b['class_id'], $this->userId(), $date]);
        $this->created(['id' => (int)$this->db->lastInsertId()]);
    }

    // POST /attendance/sessions/{id}/close
    public function closeSession(array $p): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $this->db->prepare('UPDATE attendance_sessions SET closed_at=NOW() WHERE id=?')->execute([(int)$p['id']]);
        $this->ok();
    }

    // GET /attendance/sessions/{id}/records
    public function sessionRecords(array $p): void
    {
        $stmt = $this->db->prepare("
            SELECT att.*, CONCAT(u.first_name,' ',u.last_name) AS student_name, u.avatar
            FROM attendance att JOIN users u ON u.id=att.student_id
            WHERE att.session_id=? ORDER BY student_name
        ");
        $stmt->execute([(int)$p['id']]);
        $this->ok(['records' => $stmt->fetchAll()]);
    }

    // POST /attendance/sessions/{id}/mark
    public function markAttendance(array $p): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        $this->require($b, ['student_id', 'status']);
        if (!in_array($b['status'], ['present','absent','late'], true)) Response::unprocessable('status must be present, absent, or late.');

        $sid = (int)$p['id'];
        $studentId = (int)$b['student_id'];

        $this->db->prepare("
            INSERT INTO attendance (session_id,student_id,status,note)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note), marked_at=NOW()
        ")->execute([$sid, $studentId, $b['status'], $b['note'] ?? null]);

        // Award points for attendance
        if ($b['status'] === 'present') {
            $this->db->prepare('INSERT IGNORE INTO points (student_id,amount,reason,note) VALUES (?,10,"attendance","Class attendance")')->execute([$studentId]);
        }

        $this->ok();
    }

    // POST /attendance/sessions/{id}/bulk-mark
    public function bulkMarkAttendance(array $p): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        if (empty($b['records']) || !is_array($b['records'])) Response::unprocessable('records[] required.');

        $sid = (int)$p['id'];
        $stmt = $this->db->prepare("
            INSERT INTO attendance (session_id,student_id,status,note)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note), marked_at=NOW()
        ");
        foreach ($b['records'] as $rec) {
            if (empty($rec['student_id']) || empty($rec['status'])) continue;
            if (!in_array($rec['status'], ['present','absent','late'], true)) continue;
            $stmt->execute([$sid, (int)$rec['student_id'], $rec['status'], $rec['note'] ?? null]);
        }
        $this->ok();
    }

    // ── Evaluations ────────────────────────────────────────────────────────────

    // GET /evaluations (head coach sees all, coach sees own students)
    public function listEvaluations(): void
    {
        $studentId = $this->query('student_id');
        $from      = $this->query('from', date('Y-m-01'));
        $to        = $this->query('to',   date('Y-m-t'));
        $where = ['ev.eval_date BETWEEN ? AND ?'];
        $bind  = [$from, $to];

        if ($this->userRole() === 'coach') { $where[] = 'ev.coach_id=?'; $bind[] = $this->userId(); }
        if ($studentId) { $where[] = 'ev.student_id=?'; $bind[] = (int)$studentId; }

        $stmt = $this->db->prepare("
            SELECT ev.*,
                   CONCAT(s.first_name,' ',s.last_name) AS student_name, s.avatar AS student_avatar,
                   CONCAT(c.first_name,' ',c.last_name) AS coach_name
            FROM evaluations ev
            JOIN users s ON s.id=ev.student_id
            JOIN users c ON c.id=ev.coach_id
            WHERE ".implode(' AND ',$where)."
            ORDER BY ev.eval_date DESC, ev.created_at DESC
        ");
        $stmt->execute($bind);
        $this->ok(['evaluations' => $stmt->fetchAll()]);
    }

    // POST /evaluations
    public function createEvaluation(): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        $this->require($b, ['student_id', 'eval_date']);

        $fields = ['technique','fitness','discipline','focus','attitude','balance','reflex','speed','flexibility'];
        $scores = [];
        foreach ($fields as $f) {
            $v = isset($b[$f]) ? max(1, min(10, (int)$b[$f])) : 0;
            $scores[$f] = $v;
        }
        $overall = count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;

        $this->db->prepare("
            INSERT INTO evaluations (student_id,coach_id,technique,fitness,discipline,focus,attitude,balance,reflex,speed,flexibility,overall,notes,eval_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            (int)$b['student_id'], $this->userId(),
            $scores['technique'], $scores['fitness'], $scores['discipline'],
            $scores['focus'], $scores['attitude'], $scores['balance'],
            $scores['reflex'], $scores['speed'], $scores['flexibility'],
            $overall, $b['notes'] ?? null, $b['eval_date'],
        ]);

        $evId = (int)$this->db->lastInsertId();
        // Notify student
        $this->notify((int)$b['student_id'], 'evaluation', 'New evaluation recorded', 'Your coach has submitted a new performance evaluation.');

        $this->created(['id' => $evId, 'overall' => $overall]);
    }

    // GET /evaluations/{id}
    public function showEvaluation(array $p): void
    {
        $stmt = $this->db->prepare("
            SELECT ev.*, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                   CONCAT(c.first_name,' ',c.last_name) AS coach_name
            FROM evaluations ev JOIN users s ON s.id=ev.student_id JOIN users c ON c.id=ev.coach_id
            WHERE ev.id=?
        ");
        $stmt->execute([(int)$p['id']]);
        $ev = $stmt->fetch();
        if (!$ev) Response::notFound('Evaluation not found.');
        $this->ok(['evaluation' => $ev]);
    }

    // ── Skill tracking ─────────────────────────────────────────────────────────

    // GET /students/{id}/skills
    public function studentSkills(array $p): void
    {
        $stmt = $this->db->prepare("
            SELECT bs.id, bs.name, bs.belt_id, b.name AS belt_name,
                   COALESCE(ss.level,'not_started') AS level, ss.updated_at
            FROM belt_skills bs
            JOIN belts b ON b.id=bs.belt_id
            JOIN student_profiles sp ON sp.discipline_id=b.discipline_id AND sp.user_id=?
            LEFT JOIN student_skills ss ON ss.skill_id=bs.id AND ss.student_id=?
            ORDER BY b.sort_order, bs.sort_order
        ");
        $stmt->execute([(int)$p['id'], (int)$p['id']]);
        $this->ok(['skills' => $stmt->fetchAll()]);
    }

    // PUT /students/{id}/skills/{skillId}
    public function updateSkill(array $p): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        $this->require($b, ['level']);
        $levels = ['not_started','developing','competent','advanced'];
        if (!in_array($b['level'], $levels, true)) Response::unprocessable('Invalid skill level.');

        $this->db->prepare("
            INSERT INTO student_skills (student_id,skill_id,level,updated_by)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE level=VALUES(level), updated_by=VALUES(updated_by), updated_at=NOW()
        ")->execute([(int)$p['id'], (int)$p['skillId'], $b['level'], $this->userId()]);
        $this->ok();
    }

    // ── Promotions ─────────────────────────────────────────────────────────────

    // GET /promotions
    public function listPromotions(): void
    {
        $status = $this->query('status', 'pending');
        $stmt = $this->db->prepare("
            SELECT pr.*,
                   CONCAT(s.first_name,' ',s.last_name) AS student_name,
                   bf.name AS from_belt, bt.name AS to_belt,
                   CONCAT(a.first_name,' ',a.last_name) AS approved_by_name
            FROM promotions pr
            JOIN users s ON s.id=pr.student_id
            LEFT JOIN belts bf ON bf.id=pr.from_belt_id
            JOIN belts bt ON bt.id=pr.to_belt_id
            JOIN users a ON a.id=pr.approved_by
            WHERE pr.status=?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$status]);
        $this->ok(['promotions' => $stmt->fetchAll()]);
    }

    // POST /promotions
    public function createPromotion(): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['student_id', 'to_belt_id']);

        $profile = $this->db->prepare('SELECT current_belt_id FROM student_profiles WHERE user_id=?');
        $profile->execute([(int)$b['student_id']]);
        $sp = $profile->fetch();

        $this->db->prepare("
            INSERT INTO promotions (student_id,from_belt_id,to_belt_id,approved_by,status,notes,promoted_at)
            VALUES (?,?,?,?,'approved',?,NOW())
        ")->execute([(int)$b['student_id'], $sp['current_belt_id'] ?? null, (int)$b['to_belt_id'], $this->userId(), $b['notes'] ?? null]);

        // Update student's current belt
        $this->db->prepare('UPDATE student_profiles SET current_belt_id=? WHERE user_id=?')->execute([(int)$b['to_belt_id'], (int)$b['student_id']]);
        // Award promotion points
        $this->db->prepare('INSERT INTO points (student_id,amount,reason,note) VALUES (?,200,"promotion","Belt promotion")')->execute([(int)$b['student_id']]);
        // Notify student
        $belt = $this->db->prepare('SELECT name FROM belts WHERE id=?');
        $belt->execute([(int)$b['to_belt_id']]);
        $beltName = $belt->fetchColumn();
        $this->notify((int)$b['student_id'], 'promotion', 'Belt Promotion!', "Congratulations! You have been promoted to $beltName.");

        $this->created(['id' => (int)$this->db->lastInsertId()]);
    }

    // ── Seminars ───────────────────────────────────────────────────────────────

    // GET /seminars
    public function listSeminars(): void
    {
        $stmt = $this->db->query('SELECT s.*, CONCAT(u.first_name," ",u.last_name) AS created_by_name FROM seminars s JOIN users u ON u.id=s.created_by ORDER BY s.date DESC');
        $this->ok(['seminars' => $stmt->fetchAll()]);
    }

    // POST /seminars
    public function createSeminar(): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['title', 'date']);
        $this->db->prepare('INSERT INTO seminars (title,description,date,location,points,created_by) VALUES (?,?,?,?,?,?)')->execute([$b['title'],$b['description']??null,$b['date'],$b['location']??null,(int)($b['points']??50),$this->userId()]);
        $this->created(['id' => (int)$this->db->lastInsertId()]);
    }

    // POST /seminars/{id}/attend
    public function markSeminarAttendance(array $p): void
    {
        $this->requireRole('admin', 'head_coach', 'coach');
        $b = $this->body();
        $this->require($b, ['student_id']);
        $seminar = $this->db->prepare('SELECT points FROM seminars WHERE id=?');
        $seminar->execute([(int)$p['id']]);
        $sem = $seminar->fetch();
        if (!$sem) Response::notFound('Seminar not found.');

        try {
            $this->db->prepare('INSERT INTO seminar_attendance (seminar_id,student_id) VALUES (?,?)')->execute([(int)$p['id'], (int)$b['student_id']]);
            $this->db->prepare('INSERT INTO points (student_id,amount,reason,note) VALUES (?,?,"seminar","Seminar attendance")')->execute([(int)$b['student_id'], $sem['points']]);
        } catch (\Throwable) {}
        $this->ok();
    }

    // ── Analytics ──────────────────────────────────────────────────────────────

    // GET /analytics/dashboard
    public function analyticsDashboard(): void
    {
        $this->requireRole('admin', 'head_coach');

        $totalStudents = (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn();
        $totalCoaches  = (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role='coach'   AND is_active=1")->fetchColumn();
        $pendingPromos = (int)$this->db->query("SELECT COUNT(*) FROM promotions WHERE status='pending'")->fetchColumn();

        // Average attendance last 30 days — use double-wrap to avoid MySQL alias restriction
        $avgRow = $this->db->query("
            SELECT ROUND(AVG(pct), 1)
            FROM (
                SELECT
                    att.student_id,
                    ROUND(
                        SUM(CASE WHEN att.status='present' THEN 1 WHEN att.status='late' THEN 0.5 ELSE 0 END)
                        / COUNT(*) * 100
                    , 1) AS pct
                FROM attendance att
                INNER JOIN attendance_sessions s ON s.id = att.session_id
                WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY att.student_id
            ) sub
        ")->fetchColumn();
        $avgAttendance = round((float)$avgRow, 1);

        // Promotion-ready students (pending promotions)
        $ready = $this->db->query("
            SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name,
                   b.name AS current_belt, b.color_hex
            FROM promotions p
            JOIN users u ON u.id = p.student_id
            JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN belts b ON b.id = sp.current_belt_id
            WHERE p.status = 'pending' AND u.is_active = 1
            ORDER BY p.created_at DESC
            LIMIT 5
        ")->fetchAll();

        // Low attendance students (< 70% last 30 days)
        $lowAtt = $this->db->query("
            SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name,
                   ROUND(
                       SUM(CASE WHEN att.status='present' THEN 1 WHEN att.status='late' THEN 0.5 ELSE 0 END)
                       / COUNT(*) * 100
                   , 1) AS pct
            FROM users u
            INNER JOIN attendance att ON att.student_id = u.id
            INNER JOIN attendance_sessions s ON s.id = att.session_id
            WHERE u.role = 'student' AND u.is_active = 1
              AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY u.id, u.first_name, u.last_name
            HAVING pct < 70
            ORDER BY pct ASC
            LIMIT 5
        ")->fetchAll();

        $this->ok([
            'totals'             => ['students' => $totalStudents, 'coaches' => $totalCoaches, 'pending_promotions' => $pendingPromos],
            'avg_attendance_30d' => $avgAttendance,
            'promotion_pipeline' => $ready,
            'low_attendance'     => $lowAtt,
        ]);
    }

    // GET /analytics/coach-report
    public function coachReport(): void
    {
        $this->requireRole('coach', 'head_coach', 'admin');
        $coachId = $this->userRole() === 'coach' ? $this->userId() : (int)$this->query('coach_id', $this->userId());
        $from    = $this->query('from', date('Y-m-01'));
        $to      = $this->query('to',   date('Y-m-t'));

        $sessions = $this->db->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE coach_id=? AND session_date BETWEEN ? AND ?")->execute([$coachId,$from,$to]) && 0;
        $s = $this->db->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE coach_id=? AND session_date BETWEEN ? AND ?");
        $s->execute([$coachId,$from,$to]); $sessionCount = (int)$s->fetchColumn();

        $e = $this->db->prepare("SELECT COUNT(*) FROM evaluations WHERE coach_id=? AND eval_date BETWEEN ? AND ?");
        $e->execute([$coachId,$from,$to]); $evalCount = (int)$e->fetchColumn();

        $this->ok(['sessions' => $sessionCount, 'evaluations' => $evalCount, 'period' => ['from' => $from, 'to' => $to]]);
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function notify(int $userId, string $type, string $title, string $body): void
    {
        try {
            $this->db->prepare('INSERT INTO notifications (user_id,type,title,body) VALUES (?,?,?,?)')->execute([$userId, $type, $title, $body]);
        } catch (\Throwable) {}
    }
}
