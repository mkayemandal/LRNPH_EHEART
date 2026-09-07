/* =========================================================
   eHEART DATABASE
   UPDATED / REFACTORED SCHEMA
    VERSION: 2026-09
   ========================================================= */


/* =========================================================
   1. eheart_role
   ========================================================= */

CREATE TABLE eheart_role (
    role_id INT IDENTITY(1,1) NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_role PRIMARY KEY (role_id),
    CONSTRAINT UQ_eheart_role_name UNIQUE (role_name)
);
GO


INSERT INTO eheart_role (role_name)
VALUES
    ('Manager'),
    ('HR Admin'),
    ('System Administrator');
GO


/* =========================================================
   2. eheart_user
   ========================================================= */

CREATE TABLE eheart_user (
    user_id INT IDENTITY(1,1) NOT NULL,
    biometric_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    last_login_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_user
        PRIMARY KEY (user_id),

    CONSTRAINT UQ_eheart_user_biometric_id
        UNIQUE (biometric_id),

    CONSTRAINT FK_eheart_user_role
        FOREIGN KEY (role_id)
        REFERENCES eheart_role(role_id)
);
GO


CREATE INDEX IX_eheart_user_role_id
ON eheart_user(role_id);
GO


CREATE INDEX IX_eheart_user_is_active
ON eheart_user(is_active);
GO

INSERT INTO dbo.eheart_user
(
    biometric_id,
    full_name,
    email,
    password_hash,
    role_id,
    is_active
)
VALUES
(
    '42679',
    'Mariel Kaye Mandal',
    'marielkayemandal30@gmail.com',
    '$2y$10$2JVxmOCn7V3t6yUJ5p3pwuv/1Yl1xvmOGld0nWYqhrS6M8.MkoOmi',
    (
        SELECT role_id
        FROM dbo.eheart_role
        WHERE role_name = 'System Administrator'
    ),
    1
);
GO


/* =========================================================
   3. eheart_core_value
   ========================================================= */

CREATE TABLE eheart_core_value (
    core_value_id INT IDENTITY(1,1) NOT NULL,
    core_value_name VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_core_value
        PRIMARY KEY (core_value_id),

    CONSTRAINT UQ_eheart_core_value_name
        UNIQUE (core_value_name)
);
GO


INSERT INTO eheart_core_value
(
    core_value_name,
    description
)
VALUES
(
    'Excellence',
    'I deliver high quality and pursue continuous improvement'
),
(
    'Efficiency',
    'I work smart and remove delays to achieve more'
),
(
    'Teamwork',
    'I support others and achieve together'
),
(
    'Professionalism',
    'I act with discipline, integrity, and respect'
),
(
    'Passion',
    'I show initiative, energy, and pride in my work.'
);
GO


/* =========================================================
   4. eheart_heart_card
   ========================================================= */

CREATE TABLE eheart_heart_card (
    heart_card_id BIGINT IDENTITY(1,1) NOT NULL,

    /* Requester / Manager */
    requester_biometric_id VARCHAR(50) NOT NULL,
    requester_full_name VARCHAR(200) NULL,

    /* Receiver / Employee */
    receiver_biometric_id VARCHAR(50) NOT NULL,
    receiver_full_name VARCHAR(200) NOT NULL,
    receiver_department VARCHAR(200) NULL,

    /* Recognition Details */
    core_values NVARCHAR(MAX) NOT NULL,
    action_performed NVARCHAR(MAX) NOT NULL,
    business_impact NVARCHAR(MAX) NOT NULL,
    why_beyond_normal NVARCHAR(MAX) NOT NULL,
    short_inspiring_note NVARCHAR(1000) NULL,

    /* Workflow */
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',

    /* Review */
    reviewed_by_biometric_id VARCHAR(50) NULL,
    reviewed_at DATETIME2 NULL,
    review_feedback NVARCHAR(MAX) NULL,

    /* Expiration */
    expiry_date DATE NULL,
    qr_code_path VARCHAR(500) NULL,

    /* System Dates */
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_heart_card
        PRIMARY KEY (heart_card_id),

    CONSTRAINT CK_eheart_heart_card_core_values
        CHECK (ISJSON(core_values) = 1),

    CONSTRAINT CK_eheart_heart_card_status
        CHECK (
            status IN (
                'PENDING',
                'APPROVED',
                'FOR_REVISION',
                'REJECTED',
                'NOT_QUALIFIED',
                'CANCELLED',
                'ACTIVE',
                'FOR_REDEMPTION',
                'REDEEMED',
                'EXPIRED'
            )
        )
);
GO


CREATE INDEX IX_eheart_heart_card_requester
ON eheart_heart_card(requester_biometric_id);
GO


CREATE INDEX IX_eheart_heart_card_receiver
ON eheart_heart_card(receiver_biometric_id);
GO


CREATE INDEX IX_eheart_heart_card_department
ON eheart_heart_card(receiver_department);
GO


CREATE INDEX IX_eheart_heart_card_status
ON eheart_heart_card(status);
GO


CREATE INDEX IX_eheart_heart_card_expiry
ON eheart_heart_card(expiry_date);
GO


CREATE INDEX IX_eheart_heart_card_created_at
ON eheart_heart_card(created_at);
GO


/* =========================================================
   5. eheart_department_quota
   ========================================================= */

CREATE TABLE eheart_department_quota (
    department_quota_id INT IDENTITY(1,1) NOT NULL,
    department VARCHAR(200) NOT NULL,
    department_normalized AS UPPER(LTRIM(RTRIM(REPLACE(department, '-', ' ')))) PERSISTED,
    monthly_quota INT NOT NULL,
    headcount INT NOT NULL DEFAULT 0,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_department_quota
        PRIMARY KEY (department_quota_id),

    CONSTRAINT UQ_eheart_department_quota_normalized
        UNIQUE (department_normalized),

    CONSTRAINT CK_eheart_department_quota_department
        CHECK (LEN(LTRIM(RTRIM(department))) > 0),

    CONSTRAINT CK_eheart_department_quota_monthly_quota
        CHECK (monthly_quota > 0),

    CONSTRAINT CK_eheart_department_quota_headcount
        CHECK (headcount >= 0)
);
GO


CREATE INDEX IX_eheart_department_quota_is_active
ON eheart_department_quota(is_active);
GO


/* =========================================================
   6. eheart_system_setting
   ========================================================= */

CREATE TABLE eheart_system_setting (
    setting_id INT IDENTITY(1,1) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value VARCHAR(100) NOT NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_system_setting
        PRIMARY KEY (setting_id),

    CONSTRAINT UQ_eheart_system_setting_key
        UNIQUE (setting_key),

    CONSTRAINT CK_eheart_system_setting_key
        CHECK (LEN(LTRIM(RTRIM(setting_key))) > 0),

    CONSTRAINT CK_eheart_system_setting_value
        CHECK (LEN(LTRIM(RTRIM(setting_value))) > 0)
);
GO


INSERT INTO eheart_system_setting (setting_key, setting_value)
VALUES
    ('MANAGER_MONTHLY_REQUEST_LIMIT', '10'),
    ('EMPLOYEE_MONTHLY_EHEART_LIMIT', '1');
GO


/* =========================================================
    7. eheart_redemption
   =========================================================
   This table now replaces eheart_gift_certificate.

   It stores:
   - Heart Card number
   - Gift Certificate amount
   - Employee receiving the redemption
   - HR employee processing the redemption
   - Redemption processing information
   ========================================================= */

CREATE TABLE eheart_redemption (
    redemption_id BIGINT IDENTITY(1,1) NOT NULL,

    /* Heart Card */
    heart_card_id BIGINT NOT NULL,
    heart_card_number VARCHAR(100) NOT NULL,

    /* Employee / Receiver */
    employee_biometric_id VARCHAR(50) NOT NULL,
    employee_name VARCHAR(200) NOT NULL,

    /* Gift Certificate */
    gc_serial_number VARCHAR(100) NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 300.00,

    /* HR Processor */
    processed_by_biometric_id VARCHAR(50) NULL,
    processed_by_name VARCHAR(200) NULL,

    /* Redemption Processing */
    redeemed_at DATETIME2 NULL,
    processed_at DATETIME2 NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',

    validation_notes NVARCHAR(1000) NULL,

    /* System Dates */
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_redemption
        PRIMARY KEY (redemption_id),

    CONSTRAINT UQ_eheart_redemption_heart_card
        UNIQUE (heart_card_id),

    CONSTRAINT UQ_eheart_redemption_heart_card_number
        UNIQUE (heart_card_number),

    CONSTRAINT FK_eheart_redemption_heart_card
        FOREIGN KEY (heart_card_id)
        REFERENCES eheart_heart_card(heart_card_id),

    CONSTRAINT CK_eheart_redemption_amount
        CHECK (amount > 0),

    CONSTRAINT CK_eheart_redemption_status
        CHECK (
            status IN (
                'PENDING',
                'VALIDATED',
                'REJECTED',
                'CANCELLED',
                'REDEEMED'
            )
        )
);
GO


CREATE INDEX IX_eheart_redemption_employee
ON eheart_redemption(employee_biometric_id);
GO


CREATE INDEX IX_eheart_redemption_status
ON eheart_redemption(status);
GO


CREATE INDEX IX_eheart_redemption_processed_by
ON eheart_redemption(processed_by_biometric_id);
GO


CREATE INDEX IX_eheart_redemption_redeemed_at
ON eheart_redemption(redeemed_at);
GO


CREATE INDEX IX_eheart_redemption_processed_at
ON eheart_redemption(processed_at);
GO


/* =========================================================
    8. eheart_audit_log
   ========================================================= */

CREATE TABLE eheart_audit_log (
    audit_log_id BIGINT IDENTITY(1,1) NOT NULL,

    user_biometric_id VARCHAR(50) NULL,

    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NOT NULL,

    reference_id VARCHAR(100) NULL,

    description NVARCHAR(MAX) NULL,

    ip_address VARCHAR(45) NULL,

    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_audit_log
        PRIMARY KEY (audit_log_id)
);
GO


CREATE INDEX IX_eheart_audit_user
ON eheart_audit_log(user_biometric_id);
GO


CREATE INDEX IX_eheart_audit_action
ON eheart_audit_log(action);
GO


CREATE INDEX IX_eheart_audit_module
ON eheart_audit_log(module);
GO


CREATE INDEX IX_eheart_audit_reference
ON eheart_audit_log(reference_id);
GO


CREATE INDEX IX_eheart_audit_created_at
ON eheart_audit_log(created_at);
GO


/* =========================================================
    9. eheart_notification
   ========================================================= */

CREATE TABLE eheart_notification (
    notification_id BIGINT IDENTITY(1,1) NOT NULL,

    recipient_biometric_id VARCHAR(50) NOT NULL,

    notification_type VARCHAR(50) NOT NULL,

    title VARCHAR(255) NOT NULL,

    message NVARCHAR(MAX) NOT NULL,

    reference_type VARCHAR(100) NULL,
    reference_id VARCHAR(100) NULL,

    is_read BIT NOT NULL DEFAULT 0,
    read_at DATETIME2 NULL,

    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    CONSTRAINT PK_eheart_notification
        PRIMARY KEY (notification_id)
);
GO


CREATE INDEX IX_eheart_notification_recipient
ON eheart_notification(recipient_biometric_id);
GO


CREATE INDEX IX_eheart_notification_unread
ON eheart_notification(recipient_biometric_id, is_read);
GO


CREATE INDEX IX_eheart_notification_created_at
ON eheart_notification(created_at);
GO


CREATE INDEX IX_eheart_notification_reference_lookup
ON eheart_notification(
    recipient_biometric_id,
    notification_type,
    reference_type,
    reference_id
);
GO
