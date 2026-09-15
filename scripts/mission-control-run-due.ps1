# Mission Control CLI Automation Runner for Windows PowerShell
# Usage:
#   powershell -ExecutionPolicy Bypass -File scripts\mission-control-run-due.ps1

$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $ProjectRoot

php scripts\mission-control-run-due.php --due
exit $LASTEXITCODE
