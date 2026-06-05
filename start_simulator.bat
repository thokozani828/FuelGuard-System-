@echo off
cd /d "%~dp0"
echo Starting FuelGuard Sensor Simulator...
echo.
python -m simulator.sensor_simulator --device-id tank_001 --interval 3
pause