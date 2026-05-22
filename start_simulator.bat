@echo off
cd C:\xampp\htdocs\fuelGued
echo Starting FuelGuard Sensor Simulator...
echo.
python -m simulator.sensor_simulator --device-id tank_001 --interval 3
pause