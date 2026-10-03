<?php

/**
 * Front controller. Every route the app has lives in this one table —
 * per CLAUDE.md's "controllers stay thin" and PROJECT_STRUCTURE.md.
 */

// Under `php -S ... public/index.php` every request comes here first: let the built-in
// server send real files in public/ (CSS, JS, images) itself. Everything else, including
// /uploads/... (kept outside public/), goes through the routes below.
if (PHP_SAPI === 'cli-server') {
    $static = realpath(__DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    if ($static !== false && is_file($static) && str_starts_with($static, __DIR__ . DIRECTORY_SEPARATOR) && !str_ends_with($static, '.php')) {
        return false;
    }
}

ini_set('session.use_strict_mode', '1'); // reject session IDs this server didn't issue
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
// Views still use inline <script>/<style> and the landing page embeds remote
// fonts/video/map; those allowances go once the views are cleaned up.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
    . "img-src 'self' data: blob:; media-src 'self' https://d8j0ntlcm91z4.cloudfront.net; "
    . "frame-src https://maps.google.com https://www.google.com; connect-src 'self'; "
    . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

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
use App\Controllers\LandingController;
use App\Controllers\AssistantController;
use App\Controllers\InquiryController;
use App\Controllers\StaffController;

$router = new Router();

// --- Landing Page ---
$router->add('GET', '/', [LandingController::class, 'index']);
$router->add('GET', '/landing', [LandingController::class, 'index']);
$router->add('POST', '/inquire', [LandingController::class, 'handleInquiry']);

// --- Auth ---
$router->add('GET', '/login', [AuthController::class, 'showLogin']);
$router->add('POST', '/login', [AuthController::class, 'login']);
$router->add('POST', '/logout', [AuthController::class, 'logout']);

// --- Admin (Phase 1 RBAC: admin only) ---
$router->add('GET', '/admin/dashboard', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    DashboardController::admin();
});
$router->add('GET', '/admin/inquiry-center', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin', 'staff']);
    InquiryController::adminCenter();
});
$router->add('POST', '/admin/inquiry-center/submit', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin', 'staff']);
    InquiryController::handleStaffInquiry();
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
$router->add('POST', '/admin/boarders/{id}/restore', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::restore($id);
});
$router->add('GET', '/admin/boarders/{id}', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::show($id);
});
$router->add('POST', '/admin/boarders/{id}/dates', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::updateDates($id);
});
$router->add('POST', '/admin/boarders/{id}/notes', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    BoarderController::updateNotes($id);
});
$router->add('GET', '/admin/staff', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    StaffController::index();
});
$router->add('POST', '/admin/staff', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    StaffController::create();
});
$router->add('POST', '/admin/staff/{id}/status', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    StaffController::setStatus($id);
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
$router->add('POST', '/admin/penalties/issue', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::issuePenalty();
});
$router->add('POST', '/admin/penalties/{id}/mark-paid', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['admin']);
    PenaltyController::markPaid($id);
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
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin', 'boarder']);
    IncidentController::index();
});
$router->add('POST', '/staff/incidents', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin', 'boarder']);
    IncidentController::create();
});
$router->add('POST', '/staff/incidents/{id}/resolve', function ($id) {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    IncidentController::resolve($id);
});
$router->add('GET', '/staff/incidents/history', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin', 'boarder']);
    IncidentController::history();
});
$router->add('POST', '/staff/penalties/issue', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    PenaltyController::issuePenalty();
});
$router->add('GET', '/portal/incidents', function () {
    AuthMiddleware::require();
    IncidentController::index();
});
$router->add('POST', '/portal/incidents', function () {
    AuthMiddleware::require();
    IncidentController::create();
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
$router->add('POST', '/api/maintenance/score-preview', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    MaintenanceController::scorePreview();
});
$router->add('GET', '/portal/payments/new', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    PaymentController::newForm();
});
$router->add('POST', '/portal/payments', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    PaymentController::create();
});

// --- Uploaded receipts / repair photos (stored outside public/, access-checked) ---
$router->add('GET', '/uploads/{subdir}/{file}', function ($subdir, $file) {
    AuthMiddleware::require();
    \App\Support\Uploads::serve($subdir, $file);
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

// --- AI Assistant APIs (Ollama Integration) ---
$router->add('GET', '/api/assistant/status', function () {
    AuthMiddleware::require();
    AssistantController::status();
});
$router->add('POST', '/api/assistant/chat', function () {
    AuthMiddleware::require();
    AssistantController::chat();
});
$router->add('POST', '/api/assistant/suggest-description', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    AssistantController::suggestDescription();
});
$router->add('POST', '/api/assistant/suggest-category', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['boarder']);
    AssistantController::suggestCategory();
});
$router->add('POST', '/api/assistant/analyze-ticket', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    AssistantController::analyzeTicket();
});
$router->add('POST', '/api/assistant/summarize-queue', function () {
    AuthMiddleware::require(); RoleMiddleware::require(['staff', 'admin']);
    AssistantController::summarizeQueue();
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
