@echo off
cd C:\xampp\htdocs\fuelGued
echo Starting FuelGuard API Server...
echo.
uvicorn gateway.api:app --reload --host 0.0.0.0 --port 8000
pause