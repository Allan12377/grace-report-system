# Grace School Reports — single-computer installation

Grace School Reports is a PHP and SQLite web app for Grace Christian Primary School, Kacungwa. Apache serves it in the computer's browser; PHP and the SQLite database run on that same Windows computer. No internet connection is needed for everyday use. This setup is for **one computer only** and is not configured for access from other computers or the internet.

## What is included

- Administrator sign-in and password change
- P1–P7 classes, pupil profiles, photos, and enrolment history
- Marks entry, comments, class rankings, and printable reports
- School settings, class promotion, and certificates
- SQLite database in `storage/school.sqlite` and pupil photos in `uploads/`
- A command-line backup script that backs up both the database and photos

## Install on the school's Windows computer

These instructions use `C:\xampp`. If XAMPP is installed somewhere else, substitute its actual folder in the commands and paths below.

1. Install a current XAMPP for Windows. Apache and PHP are required; MySQL is not.
2. Copy the application files to `C:\xampp\htdocs\grace-report-system`.
   - Do **not** copy a developer/test database into `storage\school.sqlite`.
   - Do **not** copy test pupil photos from `uploads`. Keep the folder, but leave it empty for the school's new data.
   - Do not put the private backup script or backup files under `htdocs`.
3. In XAMPP Control Panel, start Apache. Open PowerShell and check that the bundled PHP has PDO SQLite support:

   ```powershell
   & 'C:\xampp\php\php.exe' -v
   & 'C:\xampp\php\php.exe' -m | Select-String 'PDO|sqlite'
   ```

   The output must include `pdo_sqlite`. If it is missing, open `C:\xampp\php\php.ini` as administrator, enable this line (remove a leading `;` if present), save, and restart Apache:

   ```ini
   extension=pdo_sqlite
   ```

4. Initialize the database from PowerShell:

   ```powershell
   & 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\grace-report-system\install.php'
   ```

   This creates an empty school database, the P1–P7 classes and subjects, and the initial administrator account. It no longer creates fake pupil records by default. Demo records can be created only when explicitly requested, with `install.php --demo`; do not use that option for the school's live database.
5. In XAMPP Control Panel, confirm Apache is listening only on the local computer. For a dedicated single-computer installation, edit `C:\xampp\apache\conf\httpd.conf` as administrator:
   - Change `Listen 80` to `Listen 127.0.0.1:8080`.
   - Change `ServerName localhost:80` to `ServerName localhost:8080` if that line is present.
   - Check that the `<Directory "C:/xampp/htdocs">` section has `AllowOverride All`, so the app's local-only and database-protection rules in `.htaccess` are applied.
   - Save, restart Apache, and check that it reports no configuration errors. If port 8080 is already in use, choose another unused port and use it consistently below.
6. Open the app on that computer at:

   `http://127.0.0.1:8080/grace-report-system/`

   It should not be accessed from another computer. The application folder rules also reject non-loopback requests when Apache honors `.htaccess`.
7. Sign in with the initial credentials:
   - Username: `admin`
   - Password: `ChangeMe123!`

   Immediately open **Security** and set a unique password of at least 10 characters. Do this before entering real pupil information. Update the school details and check subjects, grading rules, comments, report printing, and the printer before relying on official reports.

## Start the system each day

Apache must be running for the browser app to open. To start it manually, open XAMPP Control Panel and click **Start** next to Apache.

To start Apache automatically when the computer starts, run XAMPP Control Panel as administrator and use its Apache **Svc** control to install the service if one is not already installed. Then open Windows **Services**, find the Apache service, set its startup type to **Automatic**, and test after restarting Windows. Do not install a duplicate service.

Once Apache is set to start automatically, create a desktop shortcut with this target:

```text
C:\Windows\System32\cmd.exe /c start "" "http://127.0.0.1:8080/grace-report-system/"
```

If you selected a different port, change `8080` in the shortcut.

## Daily backups

The backup script is intended to run only from the command line and must be kept outside `htdocs`.

1. Create `C:\SchoolReportPrivate` and `C:\SchoolReportBackups`.
2. Move `backup.php` and `backup.cmd` from the application folder to `C:\SchoolReportPrivate`. Do not leave the backup script in `htdocs`.
3. At the end of the working day, run:

   ```powershell
   & 'C:\xampp\php\php.exe' 'C:\SchoolReportPrivate\backup.php' 'C:\xampp\htdocs\grace-report-system'
   ```

   The script creates a timestamped folder under `C:\SchoolReportBackups` containing `school.sqlite` and the `uploads` folder. It checks the copied database with SQLite before reporting success. If it reports failure, do not treat that folder as a valid backup.
4. Copy a completed backup folder to a USB drive and keep at least two copies in separate places. Protect the USB drive because pupil information is private.

For an easier daily routine, create a desktop shortcut to `C:\SchoolReportPrivate\backup.cmd`. The supplied command file assumes the default paths above; edit its three path settings if XAMPP or the application is installed elsewhere. Run the shortcut and wait for the success message before closing its window.

### Test restoring a backup

Do this before entering real data, and do not test by overwriting the live database:

1. Copy the application folder to `C:\SchoolReportRestoreTest`.
2. Keep a copy of that test folder's current database, then replace `C:\SchoolReportRestoreTest\storage\school.sqlite` with the backup's `school.sqlite`.
3. Replace the test folder's `uploads` contents with the backup's `uploads` contents.
4. Start Apache and open the test copy using its matching local URL, or test it in a separate temporary local server. Confirm pupils, photos, marks, and printed reports are present.
5. Keep the live application and live database untouched throughout this test.

Run the backup command again with a different destination as the second argument if you want to back up somewhere other than `C:\SchoolReportBackups`.

## Important data and security notes

- The live database and pupil photos are inside the application folder. Back up both; a database-only copy will not preserve photos.
- `storage\.htaccess` blocks direct web access to the SQLite database. `uploads\.htaccess` prevents scripts from running in the photo folder. Keep Apache's `AllowOverride All` setting enabled for these protections.
- Do not expose this XAMPP installation to the school network or internet. The local-only setup is not a multi-user server.
- Do not send the database, pupil photos, or USB backups to public file-sharing services.
- The initial password is public starter information; change it immediately.
- Confirm the official grading scale, subject lists, comments, calculations, and A4 print output with the headteacher before using reports officially.

## Troubleshooting

**SQLite or database error:** Confirm `pdo_sqlite` is enabled in the XAMPP `php.ini` used by Apache, then restart Apache.

**Apache does not start:** Check XAMPP's Apache logs and confirm the chosen port is unused. Keep `Listen`, `ServerName`, and the browser URL on the same port.

**The app is not available after restart:** Start Apache in XAMPP Control Panel or check the Apache Windows service.

**Pupil photo upload fails:** Confirm `uploads` exists and is writable by the Windows account running Apache. Only JPG, PNG, or WEBP images under 3 MB are accepted.

**Print layout is incorrect:** Use the browser print dialogue, select A4 and portrait orientation for reports, and check the installed printer driver.
#   g r a c e - r e p o r t - s y s t e m  
 