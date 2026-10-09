@echo off
setlocal
set "PHP=C:\xampp\php\php.exe"
set "APP=C:\xampp\htdocs\grace-report-system"
set "SCRIPT=C:\SchoolReportPrivate\backup.php"

if not exist "%PHP%" (
    echo XAMPP PHP was not found at "%PHP%".
    echo Edit this file and set PHP to the correct php.exe path.
    pause
    exit /b 1
)
if not exist "%SCRIPT%" (
    echo The private backup.php script was not found at "%SCRIPT%".
    echo Move backup.php to C:\SchoolReportPrivate and try again.
    pause
    exit /b 1
)

"%PHP%" "%SCRIPT%" "%APP%"
if errorlevel 1 (
    echo Backup failed. Do not use the incomplete backup.
    pause
    exit /b 1
)

echo Copy a completed backup folder to the USB drive.
pause
