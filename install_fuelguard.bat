@echo off
title FuelGuard Installation
cd /d "%~dp0"

echo ========================================
echo    FuelGuard System Installation
echo ========================================
echo.

:: Install Python packages
echo Installing Python packages from requirements.txt...
pip install -r requirements.txt

:: Create directories
echo.
echo Creating directories...
mkdir logs data 2>nul

echo.
echo ========================================
echo    Installation Complete!
echo ========================================
echo.
echo To start the system:
echo   1. Run: start_api.bat (Backend API)
echo   2. Run: start_fuelguard.bat (Dashboard)
echo.
pause