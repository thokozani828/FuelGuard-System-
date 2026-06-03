@echo off
title FuelGuard System
cd /d C:\xampp\htdocs\fuelGued
echo Starting FuelGuard Fleet Monitoring System...
python webDashboard.py --interval 5 --web-port 8080
pause