@echo off
title FuelGuard Python Simulator
cd /d C:\xampp\htdocs\fuelGued
echo Starting Python Integrated Simulator...
python simulator/integrated_simulator.py --interval 5 --api-port 8080
pause