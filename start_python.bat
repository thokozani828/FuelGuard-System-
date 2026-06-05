@echo off
title FuelGuard Python Simulator
cd /d "%~dp0"
echo Starting Python Integrated Simulator...
python simulator/integrated_simulator.py --interval 5 --api-port 8080
pause