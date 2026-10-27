-- User Management (/admin/staff) filters and counts residents and staff by role and account status.
ALTER TABLE users ADD INDEX idx_users_role_status (role, status);
