# CSFJ Rizal — Admission Portal

Newspaper-style front page: school services, courses offered, campus news, and Registrar services.
Logged-in staff manage news and search student records. Signed-in students can update their own contact and enrollment details.
Use the navigation tabs to switch sections without scrolling.

## Render deployment
The repository root includes a `Dockerfile` and `render.yaml` for deploying the PHP site to Render. In Render, create a new Blueprint instance and select the GitHub repository containing this project. Set the prompted `INITIAL_ADMIN_USERNAME`, `INITIAL_ADMIN_PASSWORD`, and `STUDENT_RECORDS_API_KEY` values privately in Render; never commit them.

The included Blueprint uses Render's free service plan and SQLite at `/tmp/admission_portal.sqlite`. The database is initialized with the sample news when the service starts without a database. Free services use ephemeral storage, so database changes may be lost when the service restarts or is redeployed. Do not use this setup for real student records. For persistent production data, choose a paid persistent disk or configure an external MySQL database with `DB_DRIVER=mysql` and the `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables.

The local SQLite database is intentionally excluded from Git and the Docker build because it can contain private data. Its contents will not be migrated by this Blueprint.

Keep `APP_SECRET`, `INITIAL_ADMIN_PASSWORD`, and `STUDENT_RECORDS_API_KEY` private. Generate and configure secret values in Render, not in source files.

## Local SQLite setup
SQLite runs as a local file and needs no separate database server. The project defaults to SQLite when `DB_HOST` is not set. Import `backend/schema.sqlite.sql` once to create and seed `backend/admission_portal.sqlite`. Set `DB_PATH` if you want the database file in a different location.
News images are stored in the database as validated PNG, JPEG, or WebP data URLs (maximum 2 MB and 6000 × 6000 pixels). Existing SQLite databases are upgraded automatically when the news API is first used. For an existing MySQL database, add the image column before deploying: `ALTER TABLE news ADD COLUMN image_data MEDIUMTEXT NULL;`.

The local PHP command below enables the SQLite extensions included with the Windows PHP build used during development; adjust the extension directory for your PHP installation if needed:

```powershell
$env:APP_SECRET = php -r "echo bin2hex(random_bytes(32));"
$env:INITIAL_ADMIN_USERNAME = "staff"
$securePassword = Read-Host "Choose a staff password" -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $env:INITIAL_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}
php -d extension=pdo_sqlite -d extension=sqlite3 -S 127.0.0.1:8000 backend\index.php
```

Open `http://127.0.0.1:8000/admission_portal.html`. The first staff login creates the account if `admin_accounts` is empty. Keep `APP_SECRET` and the staff password private; set a persistent random `APP_SECRET` in the host environment for any deployed site.

## Registrar link
The **Go to Registrar** button opens the Registrar service at `https://it-department-hub.onrender.com`.

## IT Hub and Student Records connection
The IT Hub checks each registered system at `GET /api/db-check`. The root `.htaccess` forwards that path to the backend health handler; `/backend/api/db-check` remains the Render health check. Send the portal's connection details to the IT Hub administrator so they can register the `web-portal` system in the Hub.

Student login and staff search go through the IT Hub. Student profile lookup uses `/hub/students/{student_id}`. Student creation and profile updates go directly to the Student Records API using the private `STUDENT_RECORDS_API_KEY`; configure it in the portal's Render environment, never in the browser or repository.

## Student registration and self-service
The backend supports student registration, sign-in, and self-service profile updates. Student credentials and profile requests are sent through the PHP backend; the Student Records API key is never sent to the browser.

Set this additional private environment variable on the PHP host:

- `STUDENT_RECORDS_API_KEY` — the private key issued for the Student Records API. If a key was pasted into chat or otherwise exposed, revoke it and configure a newly issued key here.

The API URLs default to Group 3's endpoints from the connection guide. Optional overrides are `STUDENT_RECORDS_API_URL` and `ADMIN_HUB_API_URL`. The PHP host must allow outbound HTTPS requests to both services, have `allow_url_fopen` enabled, and permit request execution for at least 80 seconds. Group 3's free servers may take 30–50 seconds to wake up; outbound requests allow up to 70 seconds.

Student IDs are generated by the portal in `YYYY-#####` format. Passwords are sent directly to the Student Records API for hashing and are not stored in the portal database. Student sign-in tokens are kept in the browser session only; the backend checks the token against the Hub before displaying or editing a profile. Staff-only student search goes through the Admin Hub.

## API
| Method | Endpoint | Access |
|---|---|---|
| POST | /backend/auth/login | public |
| POST | /backend/students/register | staff |
| POST | /backend/students/login | public |
| GET | /backend/students/{student_id}/full | signed-in student, own profile only |
| PUT | /backend/students/{student_id} | signed-in student, own profile only |
| GET | /backend/students/search?q={text} | staff |
| GET | /backend/news | public |
| POST | /backend/news | staff |
| PUT | /backend/news/{id} | staff |
| DELETE | /backend/news/{id} | staff |
