<?php

/**
 * Front controller. Every route the app has lives in this one table —
 * per CLAUDE.md's "controllers stay thin" and PROJECT_STRUCTURE.md.
 */

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => false, // set true once served over HTTPS — plain HTTP on localhost by default
]);
session_start();

require_once __DIR__ . '/../src/autoload.php';

use App\Support\Logger;

// Fixed in Phase 8 review: PHP's built-in dev server shows full stack traces
// on uncaught errors by default — fine for local debugging, a real
// information-disclosure risk if this is ever exposed beyond localhost.
// Log the real error server-side; show the visitor nothing but a generic message.
set_exception_handler(function (\Throwable $e) {
    Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Something went wrong. Please try again.';
});

use App\Support\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\BoarderController;
use App\Controllers\RoomController;
use App\Controllers\MaintenanceController;
use App\Controllers\SosController;
use App\Controllers\IncidentController;
use App\Controllers\PaymentController;
use App\Controllers\ExpenseController;
use App\Controllers\PenaltyController;
use App\Controllers\LedgerController;
use App\Controllers\OccupancyController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;

$router = new Router();

// --- Auth ---
$router->add('GET', '/login', [AuthController::class, 'showLogin']);
$router->add('POST', '/login', [AuthController::class, 'login']);
$router->add('POST', '/logout', [AuthController::class, 'logout']);
$router->add('GET', '/qr/{token}/status', [AuthController::class, 'qrStatus']);
$router->add('POST', '/qr/{token}/claim', [AuthController::class, 'claimQr']);
$router->add('GET', '/qr/{token}', [AuthController::class, 'showQr']);
$router->add('POST', '/qr/{token}', [AuthController::class, 'approveQr']);
$router->add('GET', '/', function () {
    $role = $_SESSION['role'] ?? null;
    $dest = match ($role) {
        'admin'   => '/admin/dashboard',
        'staff'   => '/staff/dashboard',
        'boarder' => '/portal/dashboard',
        default   => '/login',
    };
    header("Location: $dest");
    exit;
});

// --- Admin (Phase 1 RBAC: admin only) ---
$router->add('GET', '/admin/dashboard', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    DashboardController::admin();
});
$router->add('GET', '/admin/boarders', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::index();
});
$router->add('POST', '/admin/boarders', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::create();
});
$router->add('POST', '/admin/boarders/{id}/status', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::updateStatus($id);
});
$router->add('POST', '/admin/boarders/{id}/info', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::updateInfo($id);
});
$router->add('POST', '/admin/boarders/{id}/delete', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::delete($id);
});
$router->add('GET', '/admin/rooms', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    RoomController::index();
});
$router->add('POST', '/admin/rooms', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    RoomController::create();
});
$router->add('POST', '/admin/rooms/{id}/update', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    RoomController::update($id);
});
$router->add('POST', '/admin/rooms/{id}/delete', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    RoomController::delete($id);
});
$router->add('POST', '/admin/beds', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    RoomController::createBed();
});
$router->add('POST', '/admin/beds/{id}/update', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    RoomController::updateBed($id);
});
$router->add('POST', '/admin/beds/{id}/delete', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    RoomController::deleteBed($id);
});
$router->add('POST', '/admin/beds/assign', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::assignBed();
});
$router->add('GET', '/admin/payments', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PaymentController::index();
});
$router->add('POST', '/admin/payments/{id}/approve', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PaymentController::approve($id);
});
$router->add('POST', '/admin/payments/{id}/reject', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PaymentController::reject($id);
});
$router->add('GET', '/admin/expenses', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    ExpenseController::index();
});
$router->add('POST', '/admin/expenses', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    ExpenseController::create();
});
$router->add('GET', '/admin/penalty-rules', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::index();
});
$router->add('POST', '/admin/penalty-rules', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::createRule();
});
$router->add('POST', '/admin/penalty-rules/{id}/amount', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::updateAmount($id);
});
$router->add('POST', '/admin/penalty-rules/{id}/toggle', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::toggleActive($id);
});
$router->add('POST', '/admin/penalties/run-check', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::runCheck();
});
$router->add('POST', '/admin/notifications/rent-due', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::sendRentDueReminders();
});
$router->add('GET', '/admin/ledger/export', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    LedgerController::export();
});
$router->add('GET', '/admin/occupancy', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    OccupancyController::trend();
});

// --- Staff ---
$router->add('GET', '/staff/dashboard', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    DashboardController::staff();
});
$router->add('GET', '/staff/maintenance', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    MaintenanceController::queue();
});
$router->add('POST', '/staff/maintenance/{id}/status', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    MaintenanceController::updateStatus($id);
});
$router->add('GET', '/staff/maintenance/history', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    MaintenanceController::history();
});
$router->add('GET', '/staff/incidents', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    IncidentController::index();
});
$router->add('POST', '/staff/incidents', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    IncidentController::create();
});
$router->add('POST', '/staff/incidents/{id}/resolve', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    IncidentController::resolve($id);
});
$router->add('GET', '/staff/incidents/history', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    IncidentController::history();
});
$router->add('POST', '/staff/sos/{id}/ack', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    SosController::acknowledge($id);
});
$router->add('POST', '/staff/sos/{id}/resolve', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    SosController::resolve($id);
});

// --- Boarder portal ---
$router->add('GET', '/portal/dashboard', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    DashboardController::portal();
});
$router->add('GET', '/portal/maintenance/new', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    MaintenanceController::newForm();
});
$router->add('POST', '/portal/maintenance', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    MaintenanceController::create();
});
$router->add('GET', '/portal/payments/new', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    PaymentController::newForm();
});
$router->add('POST', '/portal/payments', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    PaymentController::create();
});

// --- Cross-role Profile & Account Management ---
$router->add('GET', '/profile', function () {
    AuthMiddleware::require();
    ProfileController::index();
});
$router->add('POST', '/profile/update', function () {
    AuthMiddleware::require();
    ProfileController::updateInfo();
});
$router->add('POST', '/profile/password', function () {
    AuthMiddleware::require();
    ProfileController::updatePassword();
});

// --- Cross-role Notifications Hub & JSON APIs ---
$router->add('GET', '/notifications', function () {
    AuthMiddleware::require();
    NotificationController::index();
});
$router->add('POST', '/notifications/read-all', function () {
    AuthMiddleware::require();
    NotificationController::markAllRead();
});
$router->add('POST', '/notifications/{id}/read', function ($id) {
    AuthMiddleware::require();
    NotificationController::markRead($id);
});
$router->add('POST', '/notifications/{id}/toggle-read', function ($id) {
    AuthMiddleware::require();
    NotificationController::toggleRead($id);
});
$router->add('POST', '/notifications/{id}/toggle-pin', function ($id) {
    AuthMiddleware::require();
    NotificationController::togglePin($id);
});
$router->add('POST', '/notifications/{id}/delete', function ($id) {
    AuthMiddleware::require();
    NotificationController::delete($id);
});
$router->add('POST', '/api/sos', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    SosController::trigger();
});
$router->add('GET', '/api/sos/active', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    SosController::activeJson();
});
$router->add('GET', '/api/notifications/unread', function () {
    AuthMiddleware::require();
    NotificationController::unreadJson();
});
$router->add('POST', '/api/notifications/{id}/read', function ($id) {
    AuthMiddleware::require();
    NotificationController::markRead($id);
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
