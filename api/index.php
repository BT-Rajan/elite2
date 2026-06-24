<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Composer autoload
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Run: composer install']);
    exit;
}
require_once $autoload;

// PSR-4 autoloader for Core / Controllers / Middleware
spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\'        => __DIR__ . '/../core/',
        'Controllers\\' => __DIR__ . '/../controllers/',
        'Middleware\\'  => __DIR__ . '/../middleware/',
    ];
    foreach ($map as $ns => $dir) {
        if (str_starts_with($class, $ns)) {
            $f = $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($f)) require_once $f;
            return;
        }
    }
});

use Core\Router;
use Core\Response;
use Controllers\AuthController;
use Controllers\AdminController;
use Controllers\StudentController;
use Controllers\CoachController;
use Controllers\NotificationController;
use Middleware\AuthMiddleware;

$r = new Router();

// ── Health ─────────────────────────────────────────────────────────────────────
$r->get('/health', fn() => Response::ok(['status' => 'ok', 'time' => date('c')]));
$r->get('/config', fn() => Response::ok(['app_url' => APP_URL]));

// ── Auth (public) ──────────────────────────────────────────────────────────────
$r->post('/auth/login',           [AuthController::class, 'login']);
$r->post('/auth/refresh',         [AuthController::class, 'refresh']);
$r->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$r->post('/auth/reset-password',  [AuthController::class, 'resetPassword']);

// ── Auth (protected) ───────────────────────────────────────────────────────────
$r->post('/auth/logout',          [AuthController::class, 'logout'],         [AuthMiddleware::class]);
$r->get('/auth/me',               [AuthController::class, 'me'],             [AuthMiddleware::class]);
$r->patch('/auth/me',             [AuthController::class, 'updateMe'],       [AuthMiddleware::class]);
$r->post('/auth/change-password', [AuthController::class, 'changePassword'], [AuthMiddleware::class]);
$r->post('/auth/register',        [AuthController::class, 'register'],       [AuthMiddleware::class]);

// ── Students ───────────────────────────────────────────────────────────────────
$r->get('/students',                          [StudentController::class, 'index'],           [AuthMiddleware::class]);
$r->get('/students/{id}',                     [StudentController::class, 'show'],            [AuthMiddleware::class]);
$r->patch('/students/{id}',                   [StudentController::class, 'update'],          [AuthMiddleware::class]);
$r->delete('/students/{id}',                  [StudentController::class, 'delete'],          [AuthMiddleware::class]);
$r->get('/students/{id}/attendance',          [StudentController::class, 'attendanceHistory'],[AuthMiddleware::class]);
$r->get('/students/{id}/belt-eligibility',    [StudentController::class, 'beltEligibility'], [AuthMiddleware::class]);
$r->get('/students/{id}/points',              [StudentController::class, 'points'],          [AuthMiddleware::class]);
$r->post('/students/{id}/points',             [StudentController::class, 'awardPoints'],     [AuthMiddleware::class]);
$r->get('/students/{id}/notes',               [StudentController::class, 'notes'],           [AuthMiddleware::class]);
$r->post('/students/{id}/notes',              [StudentController::class, 'addNote'],         [AuthMiddleware::class]);
$r->get('/students/{id}/skills',              [CoachController::class,   'studentSkills'],   [AuthMiddleware::class]);
$r->put('/students/{id}/skills/{skillId}',    [CoachController::class,   'updateSkill'],     [AuthMiddleware::class]);

// ── Classes ────────────────────────────────────────────────────────────────────
$r->get('/classes',       [CoachController::class, 'listClasses'],  [AuthMiddleware::class]);
$r->post('/classes',      [CoachController::class, 'createClass'],  [AuthMiddleware::class]);
$r->patch('/classes/{id}',[CoachController::class, 'updateClass'],  [AuthMiddleware::class]);

// ── Attendance ─────────────────────────────────────────────────────────────────
$r->get('/attendance/sessions',                    [CoachController::class, 'listSessions'],      [AuthMiddleware::class]);
$r->post('/attendance/sessions',                   [CoachController::class, 'openSession'],       [AuthMiddleware::class]);
$r->post('/attendance/sessions/{id}/close',        [CoachController::class, 'closeSession'],      [AuthMiddleware::class]);
$r->get('/attendance/sessions/{id}/records',       [CoachController::class, 'sessionRecords'],    [AuthMiddleware::class]);
$r->post('/attendance/sessions/{id}/mark',         [CoachController::class, 'markAttendance'],    [AuthMiddleware::class]);
$r->post('/attendance/sessions/{id}/bulk-mark',    [CoachController::class, 'bulkMarkAttendance'],[AuthMiddleware::class]);

// ── Evaluations ────────────────────────────────────────────────────────────────
$r->get('/evaluations',        [CoachController::class, 'listEvaluations'],  [AuthMiddleware::class]);
$r->post('/evaluations',       [CoachController::class, 'createEvaluation'], [AuthMiddleware::class]);
$r->get('/evaluations/{id}',   [CoachController::class, 'showEvaluation'],   [AuthMiddleware::class]);

// ── Promotions ─────────────────────────────────────────────────────────────────
$r->get('/promotions',   [CoachController::class, 'listPromotions'],  [AuthMiddleware::class]);
$r->post('/promotions',  [CoachController::class, 'createPromotion'], [AuthMiddleware::class]);

// ── Seminars ───────────────────────────────────────────────────────────────────
$r->get('/seminars',                [CoachController::class, 'listSeminars'],         [AuthMiddleware::class]);
$r->post('/seminars',               [CoachController::class, 'createSeminar'],        [AuthMiddleware::class]);
$r->post('/seminars/{id}/attend',   [CoachController::class, 'markSeminarAttendance'],[AuthMiddleware::class]);

// ── Analytics ──────────────────────────────────────────────────────────────────
$r->get('/analytics/dashboard',    [CoachController::class, 'analyticsDashboard'], [AuthMiddleware::class]);
$r->get('/analytics/coach-report', [CoachController::class, 'coachReport'],        [AuthMiddleware::class]);

// ── Admin ──────────────────────────────────────────────────────────────────────
$r->get('/admin/users',                              [AdminController::class, 'listUsers'],       [AuthMiddleware::class]);
$r->get('/admin/users/{id}',                         [AdminController::class, 'getUser'],        [AuthMiddleware::class]);
$r->patch('/admin/users/{id}',                       [AdminController::class, 'updateUser'],     [AuthMiddleware::class]);
$r->delete('/admin/users/{id}',                      [AdminController::class, 'deleteUser'],     [AuthMiddleware::class]);
$r->get('/admin/settings',                           [AdminController::class, 'getSettings'],    [AuthMiddleware::class]);
$r->put('/admin/settings',                           [AdminController::class, 'updateSettings'], [AuthMiddleware::class]);
$r->get('/admin/coaches',                            [AdminController::class, 'listCoaches'],    [AuthMiddleware::class]);
$r->get('/admin/coaches/{id}/students',              [AdminController::class, 'coachStudents'],  [AuthMiddleware::class]);
$r->post('/admin/coaches/{id}/students',             [AdminController::class, 'assignStudent'],  [AuthMiddleware::class]);
$r->delete('/admin/coaches/{id}/students/{studentId}',[AdminController::class, 'unassignStudent'],[AuthMiddleware::class]);
$r->get('/admin/disciplines',                        [AdminController::class, 'listDisciplines'],[AuthMiddleware::class]);
$r->post('/admin/disciplines',                       [AdminController::class, 'createDiscipline'],[AuthMiddleware::class]);
$r->patch('/admin/disciplines/{id}',                 [AdminController::class, 'updateDiscipline'],[AuthMiddleware::class]);
$r->get('/admin/disciplines/{id}/belts',             [AdminController::class, 'listBelts'],      [AuthMiddleware::class]);
$r->post('/admin/disciplines/{id}/belts',            [AdminController::class, 'createBelt'],     [AuthMiddleware::class]);
$r->patch('/admin/belts/{id}',                       [AdminController::class, 'updateBelt'],     [AuthMiddleware::class]);
$r->get('/admin/belts/{id}/skills',                  [AdminController::class, 'listBeltSkills'], [AuthMiddleware::class]);
$r->post('/admin/belts/{id}/skills',                 [AdminController::class, 'createBeltSkill'],[AuthMiddleware::class]);
$r->delete('/admin/belt-skills/{id}',                [AdminController::class, 'deleteBeltSkill'],[AuthMiddleware::class]);

// ── Notifications ──────────────────────────────────────────────────────────────
$r->get('/notifications',               [NotificationController::class, 'index'],      [AuthMiddleware::class]);
$r->patch('/notifications/{id}/read',   [NotificationController::class, 'markRead'],   [AuthMiddleware::class]);
$r->post('/notifications/read-all',     [NotificationController::class, 'markAllRead'],[AuthMiddleware::class]);
$r->delete('/notifications/{id}',       [NotificationController::class, 'delete'],     [AuthMiddleware::class]);
$r->post('/notifications/broadcast',    [NotificationController::class, 'broadcast'],  [AuthMiddleware::class]);

$r->dispatch();
