@echo off
cd /d "%~dp0"
echo Starting FuelGuard API Server...
echo.
python -m uvicorn gateway.api:app --reload --host 0.0.0.0 --port 8000
pause