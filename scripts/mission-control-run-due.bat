@echo off
REM Mission Control CLI Automation Runner for Windows Task Scheduler
REM This script assumes PHP is available in PATH.
REM If not, replace "php" below with "C:\xampp\php\php.exe".

cd /d "%~dp0.."
php scripts\mission-control-run-due.php --due
