@echo off
title FuelGuard Installation
cd /d C:\xampp\htdocs\fuelGued

echo ========================================
echo    FuelGuard System Installation
echo ========================================
echo.

:: Install Python packages
echo Installing Python packages...
pip install requests python-dotenv

:: Optional: Install FastAPI for web dashboard
echo.
echo Do you want to install web dashboard support? (y/n)
set /p install_web=
if /i "%install_web%"=="y" (
    echo Installing FastAPI and Uvicorn...
    pip install fastapi uvicorn
)

:: Create directories
echo.
echo Creating directories...
mkdir logs data 2>nul

:: Create start script
echo.
echo Creating start script...
(
echo @echo off
echo title FuelGuard System
echo cd /d C:\xampp\htdocs\fuelGued
echo python fuelguard_system.py --interval 5 --web-port 8080
echo pause
) > start_fuelguard.bat

echo.
echo ========================================
echo    Installation Complete!
echo ========================================
echo.
echo To start the system:
echo   1. Run: start_fuelguard.bat
echo   2. Open browser: http://localhost:8080/dashboard
echo.
pause