@echo off
title FuelGuard System
cd /d "%~dp0"
echo Starting FuelGuard Fleet Monitoring System...
python webDashboard.py --interval 5 --web-port 8080
pause