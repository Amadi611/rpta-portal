CREATE TABLE IF NOT EXISTS admin_account_recovery (
    user_id INT NOT NULL,
    recovery_email VARCHAR(254) NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    email_requests TINYINT UNSIGNED NOT NULL DEFAULT 0,
    email_window_started_at DATETIME NULL,
    otp_hash VARCHAR(255) NULL,
    otp_expires_at DATETIME NULL,
    otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    otp_sent_at DATETIME NULL,
    reset_token_hash CHAR(64) NULL,
    reset_token_expires_at DATETIME NULL,
    PRIMARY KEY (user_id),
    KEY idx_admin_account_recovery_locked_until (locked_until),
    CONSTRAINT fk_admin_account_recovery_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_account_recovery (user_id)
SELECT id FROM users WHERE role IN ('admin', 'super_admin');
