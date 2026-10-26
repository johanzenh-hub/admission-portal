CREATE TABLE IF NOT EXISTS admin_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS news (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL CHECK(length(title) <= 140),
  category TEXT DEFAULT '' CHECK(length(category) <= 30),
  body TEXT NOT NULL,
  image_data TEXT,
  author TEXT DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER IF NOT EXISTS news_updated_at
AFTER UPDATE ON news
FOR EACH ROW
WHEN OLD.updated_at = NEW.updated_at
BEGIN
  UPDATE news SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

INSERT INTO news (title, category, body, author, created_at) VALUES
('Intramurals 2026 Kicks Off Next Week','Intramurals','The annual intramurals will open with a parade of colleges and departments, followed by basketball, volleyball, and track events. All students are encouraged to join and cheer for their teams.','admin','2026-10-12 08:00:00'),
('Enrollment for SY 2026–2027 Now Open','Announcement','New and returning students may now enroll. Click "Proceed to Enrollment" at the top of this page to get started.','admin','2026-10-05 08:00:00'),
('Scholarship Applications Accepted Until Month-End','Scholarships','Qualified students may submit scholarship applications at the Scholarships & Financial Aid office.','admin','2026-09-28 08:00:00');

-- The first staff account is created on login from INITIAL_ADMIN_USERNAME and INITIAL_ADMIN_PASSWORD.
