-- Non-destructive upgrade for Version 5 or 6. Back up first.
USE cliniccarefive;
ALTER TABLE students ADD COLUMN IF NOT EXISTS archived_at DATETIME;
ALTER TABLE medicines ADD COLUMN IF NOT EXISTS archived_at DATETIME;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS voided_at DATETIME, ADD COLUMN IF NOT EXISTS void_reason TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS voided_at DATETIME, ADD COLUMN IF NOT EXISTS void_reason TEXT;
ALTER TABLE movements ADD COLUMN IF NOT EXISTS request_id INT NULL REFERENCES purchase_requests(id);

CREATE TABLE IF NOT EXISTS form_submissions (
 staff_id INT NOT NULL, token CHAR(32) NOT NULL, fingerprint CHAR(64) NOT NULL,
 destination VARCHAR(1000) NOT NULL, message TEXT NOT NULL,
 created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(staff_id,token), FOREIGN KEY(staff_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
