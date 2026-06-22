<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Response;

final class NotificationController extends Controller
{
    // GET /notifications
    public function index(): void
    {
        $unread = filter_var($this->query('unread', false), FILTER_VALIDATE_BOOLEAN);
        $where  = ['user_id=?'];
        $bind   = [$this->userId()];
        if ($unread) { $where[] = 'is_read=0'; }

        $stmt = $this->db->prepare('SELECT * FROM notifications WHERE '.implode(' AND ',$where).' ORDER BY created_at DESC LIMIT 50');
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
        $unreadCount = (int)$this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0')->execute([$this->userId()]) && 0;
        $uc = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $uc->execute([$this->userId()]);
        $this->ok(['notifications' => $rows, 'unread_count' => (int)$uc->fetchColumn()]);
    }

    // PATCH /notifications/{id}/read
    public function markRead(array $p): void
    {
        $this->db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int)$p['id'], $this->userId()]);
        $this->ok();
    }

    // POST /notifications/read-all
    public function markAllRead(): void
    {
        $this->db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$this->userId()]);
        $this->ok();
    }

    // DELETE /notifications/{id}
    public function delete(array $p): void
    {
        $this->db->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([(int)$p['id'], $this->userId()]);
        $this->ok();
    }

    // POST /notifications/broadcast — admin sends to all or by role
    public function broadcast(): void
    {
        $this->requireRole('admin', 'head_coach');
        $b = $this->body();
        $this->require($b, ['title', 'body']);
        $role = $b['role'] ?? null;

        $where = ['is_active=1'];
        $bind  = [];
        if ($role) { $where[] = 'role=?'; $bind[] = $role; }
        $users = $this->db->prepare('SELECT id FROM users WHERE '.implode(' AND ',$where));
        $users->execute($bind);

        $stmt = $this->db->prepare('INSERT INTO notifications (user_id,type,title,body) VALUES (?,?,?,?)');
        foreach ($users->fetchAll() as $u) {
            $stmt->execute([$u['id'], $b['type'] ?? 'general', $b['title'], $b['body']]);
        }
        $this->ok();
    }
}
