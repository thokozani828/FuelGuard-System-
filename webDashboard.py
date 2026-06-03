# webDashboard.py - FuelGuard System with Web Dashboard (Percentage-based Fuel Decrease)
import time
import random
import requests
from datetime import datetime, date, timedelta
import argparse
import threading
import os
import sys
import webbrowser
from typing import Optional, Dict, List
from dataclasses import dataclass

# Try to import FastAPI components
try:
    from fastapi import FastAPI
    from fastapi.responses import HTMLResponse
    from fastapi.middleware.cors import CORSMiddleware
    import uvicorn
    FASTAPI_AVAILABLE = True
except ImportError:
    FASTAPI_AVAILABLE = False
    print("⚠️ FastAPI not installed. Web dashboard will be disabled.")
    print("   Install with: pip install fastapi uvicorn")

from dotenv import load_dotenv

# ============================================================================
# ENVIRONMENT LOADING
# ============================================================================

def load_environment():
    possible_paths = [
        os.path.join(os.path.dirname(os.path.abspath(__file__)), '.env'),
        os.path.join(os.getcwd(), '.env'),
        '.env',
    ]
    
    for path in possible_paths:
        if os.path.exists(path):
            load_dotenv(path)
            print(f"✅ Loaded .env from: {path}")
            return True
    
    print("⚠️ No .env file found. Checking environment variables...")
    return False

load_environment()

SUPABASE_URL = os.getenv("SUPABASE_URL", "").rstrip('/')
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")

# ============================================================================
# DATA MODELS
# ============================================================================

@dataclass
class FuelTelemetry:
    device_id: str
    sensor_type: str
    raw_value_cm: float
    temperature_c: float
    fuel_level_cm: float
    fuel_percentage: float
    fuel_volume_liters: float
    status: str
    truck_id: str
    driver_name: str
    driver_id: str
    license_plate: str
    timestamp: datetime

@dataclass
class FuelLog:
    fuel_log_id: Optional[int]
    truck_id: str
    driver_id: str
    driver_name: str
    fuel_date: datetime
    fuel_amount_litres: float
    fuel_cost: float
    odometer_reading: float
    fuel_station: str

@dataclass
class Alert:
    alert_id: Optional[int]
    truck_id: str
    driver_id: str
    alert_type: str
    alert_message: str
    alert_date: datetime
    status: str
    severity: str

# ============================================================================
# DATABASE MANAGER - FIXED VERSION
# ============================================================================

class DatabaseManager:
    @staticmethod
    def get_headers():
        return {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}",
            "Content-Type": "application/json"
        }
    
    @staticmethod
    def get_active_trucks():
        if not SUPABASE_URL or not SUPABASE_KEY:
            print("❌ Supabase not configured.")
            return []
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/trucks"
            params = {"status": "eq.ACTIVE"}
            response = requests.get(url, headers=DatabaseManager.get_headers(), params=params, timeout=10)
            
            if response.status_code == 200:
                trucks = response.json()
                print(f"✅ Fetched {len(trucks)} active trucks")
                return trucks
            else:
                print(f"❌ Error fetching trucks: {response.status_code}")
                return []
        except Exception as e:
            print(f"❌ Error fetching trucks: {e}")
            return []
    
    @staticmethod
    def get_all_trucks():
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/trucks"
            response = requests.get(url, headers=DatabaseManager.get_headers(), timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching trucks: {e}")
            return []
    
    @staticmethod
    def insert_fuel_telemetry(telemetry: FuelTelemetry):
        if not SUPABASE_URL or not SUPABASE_KEY:
            return False
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
            
            data = {
                "device_id": str(telemetry.device_id) if telemetry.device_id else None,
                "sensor_type": str(telemetry.sensor_type) if telemetry.sensor_type else None,
                "raw_value_cm": float(telemetry.raw_value_cm) if telemetry.raw_value_cm is not None else 0,
                "temperature_c": float(telemetry.temperature_c) if telemetry.temperature_c is not None else 0,
                "fuel_level_cm": float(telemetry.fuel_level_cm) if telemetry.fuel_level_cm is not None else 0,
                "fuel_percentage": float(telemetry.fuel_percentage) if telemetry.fuel_percentage is not None else 0,
                "fuel_volume_liters": float(telemetry.fuel_volume_liters) if telemetry.fuel_volume_liters is not None else 0,
                "status": str(telemetry.status) if telemetry.status else "UNKNOWN",
                "truck_id": str(telemetry.truck_id) if telemetry.truck_id else None,
                "driver_name": str(telemetry.driver_name) if telemetry.driver_name else None,
                "driver_id": str(telemetry.driver_id) if telemetry.driver_id else None,
                "license_plate": str(telemetry.license_plate) if telemetry.license_plate else None,
                "timestamp": telemetry.timestamp.isoformat() if telemetry.timestamp else datetime.now().isoformat()
            }
            
            response = requests.post(url, headers=DatabaseManager.get_headers(), json=data, timeout=10)
            
            if response.status_code in [200, 201, 204]:
                print(f"  📡 Telemetry saved: {telemetry.license_plate} - {telemetry.fuel_percentage:.1f}%")
                return True
            else:
                print(f"  ❌ Telemetry save error: {response.status_code}")
                if response.text:
                    print(f"  📝 {response.text[:200]}")
                return False
        except Exception as e:
            print(f"  ❌ Telemetry error: {e}")
            return False
    
    @staticmethod
    def insert_alert(alert: Alert):
        if not SUPABASE_URL or not SUPABASE_KEY:
            return False
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/alerts"
            
            # Prepare data with proper timestamp formatting
            alert_date_str = alert.alert_date.isoformat() if alert.alert_date else datetime.now().isoformat()
            
            data = {
                "truck_id": str(alert.truck_id) if alert.truck_id else None,
                "driver_id": str(alert.driver_id) if alert.driver_id else None,
                "alert_type": str(alert.alert_type) if alert.alert_type else None,
                "alert_message": str(alert.alert_message) if alert.alert_message else None,
                "alert_date": alert_date_str,
                "status": str(alert.status) if alert.status else "Pending",
                "severity": str(alert.severity) if alert.severity else "Medium",
                "device_id": f"FMS-{alert.truck_id}" if alert.truck_id else None,
                "is_resolved": False
            }
            
            # Debug print for first few alerts
            if not hasattr(DatabaseManager, '_alert_debug_count'):
                DatabaseManager._alert_debug_count = 0
            if DatabaseManager._alert_debug_count < 3:
                print(f"  📤 Sending alert data: {data}")
                DatabaseManager._alert_debug_count += 1
            
            response = requests.post(url, headers=DatabaseManager.get_headers(), json=data, timeout=10)
            
            if response.status_code in [200, 201, 204]:
                print(f"  🔔 ALERT saved: {alert.alert_type}")
                return True
            else:
                print(f"  ❌ Alert save error: {response.status_code}")
                if response.text:
                    print(f"  📝 Response: {response.text}")
                return False
        except Exception as e:
            print(f"  ❌ Alert exception: {e}")
            return False
    
    @staticmethod
    def insert_fuel_log(fuel_log: FuelLog):
        if not SUPABASE_URL or not SUPABASE_KEY:
            return False
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/fuel_logs"
            data = {
                "truck_id": str(fuel_log.truck_id) if fuel_log.truck_id else None,
                "driver_id": str(fuel_log.driver_id) if fuel_log.driver_id else None,
                "driver_name": str(fuel_log.driver_name) if fuel_log.driver_name else None,
                "fuel_date": fuel_log.fuel_date.isoformat() if fuel_log.fuel_date else datetime.now().isoformat(),
                "fuel_amount_litres": float(fuel_log.fuel_amount_litres) if fuel_log.fuel_amount_litres else 0,
                "fuel_cost": float(fuel_log.fuel_cost) if fuel_log.fuel_cost else 0,
                "odometer_reading": float(fuel_log.odometer_reading) if fuel_log.odometer_reading else 0,
                "fuel_station": str(fuel_log.fuel_station) if fuel_log.fuel_station else None
            }
            response = requests.post(url, headers=DatabaseManager.get_headers(), json=data, timeout=10)
            if response.status_code in [200, 201, 204]:
                print(f"  📊 Fuel log saved: {fuel_log.fuel_amount_litres:.1f}L")
                return True
            else:
                print(f"  ❌ Fuel log error: {response.status_code}")
                if response.text:
                    print(f"  📝 {response.text[:200]}")
                return False
        except Exception as e:
            print(f"  ❌ Fuel log exception: {e}")
            return False
    
    @staticmethod
    def get_telemetry(limit: int = 100):
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
            params = {"order": "timestamp.desc", "limit": limit}
            response = requests.get(url, headers=DatabaseManager.get_headers(), params=params, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching telemetry: {e}")
            return []
    
    @staticmethod
    def get_alerts(limit: int = 50):
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/alerts"
            params = {"order": "alert_date.desc", "limit": limit}
            response = requests.get(url, headers=DatabaseManager.get_headers(), params=params, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching alerts: {e}")
            return []
    
    @staticmethod
    def get_fuel_logs(limit: int = 100):
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        
        try:
            url = f"{SUPABASE_URL}/rest/v1/fuel_logs"
            params = {"order": "fuel_date.desc", "limit": limit}
            response = requests.get(url, headers=DatabaseManager.get_headers(), params=params, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching fuel logs: {e}")
            return []

# ============================================================================
# TRUCK FUEL SIMULATOR - PERCENTAGE BASED
# ============================================================================

class TruckFuelSimulator:
    def __init__(self, truck: dict):
        self.truck = truck
        self.truck_id = truck.get('truck_id')
        self.license_plate = truck.get('license_plate', 'UNKNOWN')
        self.driver_name = truck.get('driver_name', 'Unknown')
        self.driver_phone = truck.get('driver_phone', '')
        self.driver_email = truck.get('driver_email', '')
        self.tank_capacity = float(truck.get('tank_capacity_liters', 300.0))
        self.tank_height_cm = float(truck.get('tank_height_cm', 120.0))
        self.current_mileage = float(truck.get('current_mileage_km', 0))
        
        self.driver_id = f"DRV{self.truck_id[-3:]}" if self.truck_id else "DRV001"
        self.device_id = f"FMS-{self.truck_id}" if self.truck_id else "FMS-001"
        
        # Fuel percentage starts at 100%
        self.fuel_percentage = 100.0
        self.fuel_volume = self.tank_capacity
        self.fuel_level_cm = self.tank_height_cm
        self.raw_value_cm = self.tank_height_cm
        self.temperature_c = 25.0
        
        # FUEL DECREASE SETTINGS - ADJUST THESE VALUES AS NEEDED
        # Each value represents percentage decrease per 5 seconds
        # Default: 0.5% decrease every 5 seconds (6% per minute)
        self.fuel_decrease_percent_per_cycle = 0.5  # Change this value to adjust speed
        self.fuel_decrease_rate = self.fuel_decrease_percent_per_cycle / 5.0  # Per second rate
        
        # Simulation state
        self.last_update_time = time.time()
        self.is_moving = True
        self.speed_kmh = random.choice([40, 50, 60, 70, 80])
        self.total_distance_km = 0
        self.total_fuel_used = 0
        self.update_count = 0
        self.last_trip_log_distance = 0
        
        # Alert tracking
        self.low_fuel_alert_sent = False
        self.critical_fuel_alert_sent = False
    
    def update_sensor_readings(self):
        """Update sensor readings based on current fuel level"""
        self.fuel_level_cm = (self.fuel_percentage / 100.0) * self.tank_height_cm
        self.raw_value_cm = self.fuel_level_cm + random.uniform(-0.5, 0.5)
        
        if self.is_moving:
            self.temperature_c = 25.0 + random.uniform(0, 15)
        else:
            self.temperature_c = 20.0 + random.uniform(-5, 5)
    
    def update_fuel_consumption(self, elapsed_seconds: float):
        """Update fuel based on percentage decrease"""
        if not self.is_moving:
            return 0
        
        # Calculate fuel decrease as percentage of tank capacity
        percent_decrease = self.fuel_decrease_rate * elapsed_seconds
        self.fuel_percentage = max(0, self.fuel_percentage - percent_decrease)
        self.fuel_volume = (self.fuel_percentage / 100.0) * self.tank_capacity
        
        # Calculate approximate distance based on fuel consumption
        new_fuel_used = (percent_decrease / 100.0) * self.tank_capacity
        self.total_fuel_used += new_fuel_used
        
        # Calculate distance traveled (approx 2.86 km per liter = 35L per 100km)
        distance_km = new_fuel_used * 2.857
        self.total_distance_km += distance_km
        self.current_mileage += distance_km
        
        self.update_sensor_readings()
        
        if self.update_count % 6 == 0:
            print(f"     [DEBUG] {self.license_plate}: -{percent_decrease:.2f}% fuel, Now: {self.fuel_percentage:.1f}%")
        
        # Random movement change (10% chance)
        if random.random() < 0.1:
            self.is_moving = not self.is_moving
            status = "MOVING" if self.is_moving else "PARKED"
            print(f"     [STATUS] {self.license_plate}: {status}")
            if self.is_moving:
                self.speed_kmh = random.choice([40, 50, 60, 70, 80])
        
        return distance_km
    
    def check_and_create_alerts(self):
        """Check fuel levels and create alerts"""
        alerts = []
        
        # Critical fuel alert (below 10%)
        if self.fuel_percentage <= 10 and not self.critical_fuel_alert_sent:
            self.critical_fuel_alert_sent = True
            alert = Alert(
                alert_id=None, truck_id=self.truck_id, driver_id=self.driver_id,
                alert_type="CRITICAL_FUEL",
                alert_message=f"CRITICAL: {self.license_plate} fuel at {self.fuel_percentage:.1f}%! Immediate refueling required!",
                alert_date=datetime.now(), status="Critical", severity="Critical"
            )
            alerts.append(alert)
            print(f"     🔴 CRITICAL FUEL ALERT for {self.license_plate}!")
        
        # Low fuel alert (below 20%)
        elif self.fuel_percentage <= 20 and not self.low_fuel_alert_sent and not self.critical_fuel_alert_sent:
            self.low_fuel_alert_sent = True
            alert = Alert(
                alert_id=None, truck_id=self.truck_id, driver_id=self.driver_id,
                alert_type="LOW_FUEL",
                alert_message=f"Low fuel: {self.license_plate} at {self.fuel_percentage:.1f}%. Please refuel soon.",
                alert_date=datetime.now(), status="Pending", severity="High"
            )
            alerts.append(alert)
            print(f"     🟡 LOW FUEL ALERT for {self.license_plate}!")
        
        # Auto-refuel when critically low and stopped (below 5%)
        if self.fuel_percentage < 5 and not self.is_moving:
            refill_amount = self.tank_capacity - self.fuel_volume
            old_percentage = self.fuel_percentage
            self.fuel_percentage = 100.0
            self.fuel_volume = self.tank_capacity
            self.update_sensor_readings()
            self.low_fuel_alert_sent = False
            self.critical_fuel_alert_sent = False
            
            fuel_log = FuelLog(
                fuel_log_id=None, truck_id=self.truck_id, driver_id=self.driver_id,
                driver_name=self.driver_name, fuel_date=datetime.now(),
                fuel_amount_litres=refill_amount, fuel_cost=refill_amount * 1.50,
                odometer_reading=self.current_mileage, fuel_station="Auto-Refuel Depot"
            )
            DatabaseManager.insert_fuel_log(fuel_log)
            
            alert = Alert(
                alert_id=None, truck_id=self.truck_id, driver_id=self.driver_id,
                alert_type="REFUELED",
                alert_message=f"Auto-refuel: {self.license_plate} +{refill_amount:.1f}L ({old_percentage:.1f}% → 100%)",
                alert_date=datetime.now(), status="Resolved", severity="Low"
            )
            alerts.append(alert)
            print(f"     🟢 AUTO-REFUEL for {self.license_plate}: +{refill_amount:.1f}L (was {old_percentage:.1f}%)")
        
        return alerts
    
    def generate_telemetry(self):
        """Generate telemetry and store in fuel_telemetry table"""
        current_time = time.time()
        elapsed = min(5, current_time - self.last_update_time)
        self.last_update_time = current_time
        
        self.update_fuel_consumption(elapsed)
        
        alerts = self.check_and_create_alerts()
        for alert in alerts:
            DatabaseManager.insert_alert(alert)
        
        if self.fuel_percentage <= 10:
            status = "CRITICAL"
        elif self.fuel_percentage <= 20:
            status = "WARNING"
        else:
            status = "NORMAL"
        
        telemetry = FuelTelemetry(
            device_id=self.device_id, sensor_type="Ultrasonic Fuel Sensor",
            raw_value_cm=round(self.raw_value_cm, 2), temperature_c=round(self.temperature_c, 1),
            fuel_level_cm=round(self.fuel_level_cm, 2), fuel_percentage=round(self.fuel_percentage, 1),
            fuel_volume_liters=round(self.fuel_volume, 1), status=status,
            truck_id=self.truck_id, driver_name=self.driver_name, driver_id=self.driver_id,
            license_plate=self.license_plate, timestamp=datetime.now()
        )
        
        DatabaseManager.insert_fuel_telemetry(telemetry)
        self.update_count += 1
        
        return {
            "truck_id": self.truck_id, "license_plate": self.license_plate,
            "driver_name": self.driver_name, "fuel_percentage": round(self.fuel_percentage, 1),
            "fuel_volume": round(self.fuel_volume, 1), "distance_km": round(self.total_distance_km, 1),
            "temperature_c": round(self.temperature_c, 1)
        }
    
    def simulate_siphon(self):
        """Simulate a siphon event - removes percentage of fuel"""
        # Remove between 15% and 30% of current fuel
        siphon_percent = random.uniform(15, 30)
        old_fuel_pct = self.fuel_percentage
        
        # Calculate fuel to remove based on percentage of current fuel
        fuel_to_remove = (siphon_percent / 100.0) * self.fuel_volume
        self.fuel_volume = max(0, self.fuel_volume - fuel_to_remove)
        self.fuel_percentage = (self.fuel_volume / self.tank_capacity) * 100
        self.update_sensor_readings()
        
        actual_removed_liters = fuel_to_remove
        actual_removed_percent = old_fuel_pct - self.fuel_percentage
        
        alert = Alert(
            alert_id=None, truck_id=self.truck_id, driver_id=self.driver_id,
            alert_type="SIPHON_DETECTED",
            alert_message=f"🚨 SIPHON ALERT! {actual_removed_liters:.1f}L ({actual_removed_percent:.1f}%) stolen from {self.license_plate}! Fuel dropped from {old_fuel_pct:.1f}% to {self.fuel_percentage:.1f}%",
            alert_date=datetime.now(), status="Pending", severity="Critical"
        )
        DatabaseManager.insert_alert(alert)
        
        # Create telemetry for siphon event
        telemetry = FuelTelemetry(
            device_id=self.device_id, sensor_type="Ultrasonic Fuel Sensor",
            raw_value_cm=round(self.raw_value_cm, 2), temperature_c=round(self.temperature_c, 1),
            fuel_level_cm=round(self.fuel_level_cm, 2), fuel_percentage=round(self.fuel_percentage, 1),
            fuel_volume_liters=round(self.fuel_volume, 1), status="SIPHON_EVENT",
            truck_id=self.truck_id, driver_name=self.driver_name, driver_id=self.driver_id,
            license_plate=self.license_plate, timestamp=datetime.now()
        )
        DatabaseManager.insert_fuel_telemetry(telemetry)
        
        print(f"\n  💀 SIPHON EVENT on {self.license_plate}!")
        print(f"     Removed: {actual_removed_liters:.1f} liters ({actual_removed_percent:.1f}%)")
        print(f"     Fuel dropped from {old_fuel_pct:.1f}% to {self.fuel_percentage:.1f}%")
        
        return actual_removed_liters
    
    def display_status(self):
        """Display current status"""
        bar_length = 20
        filled = int(bar_length * self.fuel_percentage / 100)
        bar = "█" * filled + "░" * (bar_length - filled)
        
        if self.fuel_percentage >= 75:
            color = "🟢"
        elif self.fuel_percentage >= 50:
            color = "🟡"
        elif self.fuel_percentage >= 25:
            color = "🟠"
        else:
            color = "🔴"
        
        if self.fuel_percentage <= 10:
            status_text = "CRITICAL!"
        elif self.fuel_percentage <= 20:
            status_text = "LOW"
        else:
            status_text = "OK"
        
        speed_text = f"{self.speed_kmh:.0f}km/h" if self.is_moving else "PARKED"
        
        print(f"   {self.license_plate:12} | {self.driver_name:16} | {color} {bar} | {self.fuel_percentage:6.1f}% | {self.fuel_volume:5.0f}L | {self.total_distance_km:6.1f}km | {status_text:8} | {speed_text} | {self.temperature_c:.0f}°C")

# ============================================================================
# FLEET MANAGER
# ============================================================================

class FleetManager:
    def __init__(self):
        self.trucks: Dict[str, TruckFuelSimulator] = {}
        self.running = True
        
    def load_fleet(self):
        trucks = DatabaseManager.get_active_trucks()
        
        if not trucks:
            print("\n❌ No active trucks found!")
            print("\n💡 Please run the SQL setup script in Supabase first.")
            return False
        
        for truck in trucks:
            if truck.get('status') == 'ACTIVE':
                simulator = TruckFuelSimulator(truck)
                self.trucks[truck['truck_id']] = simulator
        
        print(f"\n✅ Loaded {len(self.trucks)} active trucks")
        
        if self.trucks:
            print("\n📋 TRUCK SUMMARY:")
            print("-" * 80)
            for truck in self.trucks.values():
                print(f"   {truck.license_plate:12} | Driver: {truck.driver_name:16} | Tank: {truck.tank_capacity:3.0f}L | Decrease: {truck.fuel_decrease_percent_per_cycle:.1f}%/5s")
            print("-" * 80)
        
        return True
    
    def update_all_trucks(self):
        for truck in self.trucks.values():
            truck.generate_telemetry()
    
    def display_dashboard(self):
        os.system('cls' if os.name == 'nt' else 'clear')
        
        print("\n" + "="*110)
        print(" 🚛 FUELGUARD FLEET MONITORING SYSTEM ".center(110, "="))
        print("="*110)
        print(f" 📊 Active Trucks: {len(self.trucks)} | ⏱️  Update every 5 seconds")
        print(f" 🗄️  Database: Supabase (trucks, fuel_telemetry, alerts)")
        print(f" ⛽ Fuel decrease: {list(self.trucks.values())[0].fuel_decrease_percent_per_cycle if self.trucks else 0.5}% per 5 seconds")
        print("="*110)
        print()
        
        print(f"{'':<2} {'LICENSE PLATE':<12} {'DRIVER':<16} {'FUEL GAUGE':<24} {'%':<7} {'VOL':<6} {'DISTANCE':<8} {'STATUS':<10} {'SPEED':<8} {'TEMP'}")
        print("-"*110)
        
        for truck in self.trucks.values():
            truck.display_status()
        
        print("-"*110)
        
        alerts = DatabaseManager.get_alerts(5)
        if alerts:
            print("\n⚠️ RECENT ALERTS:")
            print("-"*110)
            for alert in alerts[:5]:
                alert_date = alert.get('alert_date', '')[:19].replace('T', ' ')
                alert_type = alert.get('alert_type', 'N/A')
                truck_id = alert.get('truck_id', 'N/A')
                message = alert.get('alert_message', '')[:60]
                
                if 'SIPHON' in alert_type:
                    print(f"   💀 [{alert_date}] {alert_type:20} | {truck_id:8} | {message}")
                elif 'CRITICAL' in alert_type:
                    print(f"   🔴 [{alert_date}] {alert_type:20} | {truck_id:8} | {message}")
                elif 'LOW' in alert_type:
                    print(f"   🟡 [{alert_date}] {alert_type:20} | {truck_id:8} | {message}")
                else:
                    print(f"   📋 [{alert_date}] {alert_type:20} | {truck_id:8} | {message}")
        
        print("\n" + "="*110)
        print(" 💡 Commands: s<number> - Simulate siphon | r - Refresh | q - Quit")
        print("="*110)
    
    def simulate_siphon(self, truck_num: int):
        truck_ids = list(self.trucks.keys())
        if 0 <= truck_num < len(truck_ids):
            truck_id = truck_ids[truck_num]
            self.trucks[truck_id].simulate_siphon()
            return True
        else:
            print(f"\n  ❌ Invalid truck number. Available: 1-{len(truck_ids)}")
            return False
    
    def run_console_ui(self, interval: int = 5):
        def update_loop():
            while self.running:
                self.update_all_trucks()
                self.display_dashboard()
                time.sleep(interval)
        
        update_thread = threading.Thread(target=update_loop, daemon=True)
        update_thread.start()
        
        while self.running:
            try:
                cmd = input().strip().lower()
                if cmd == 'q':
                    self.running = False
                    break
                elif cmd == 'r':
                    self.display_dashboard()
                elif cmd.startswith('s'):
                    try:
                        truck_num = int(cmd[1:]) - 1
                        self.simulate_siphon(truck_num)
                    except ValueError:
                        print("Use format: s1, s2, etc.")
                else:
                    print("\n  Available commands:")
                    print("    s1, s2, s3 - Simulate siphon on truck")
                    print("    r           - Refresh display")
                    print("    q           - Quit")
            except EOFError:
                break
            except KeyboardInterrupt:
                break

# ============================================================================
# FASTAPI WEB SERVER
# ============================================================================

def create_fastapi_app(manager: FleetManager):
    app = FastAPI(title="FuelGuard API", version="2.0.0")
    
    app.add_middleware(
        CORSMiddleware,
        allow_origins=["*"],
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )
    
    @app.get("/api/trucks")
    async def get_trucks():
        return DatabaseManager.get_all_trucks()
    
    @app.get("/api/alerts")
    async def get_alerts():
        return DatabaseManager.get_alerts(100)
    
    @app.get("/api/fuel-logs")
    async def get_fuel_logs():
        return DatabaseManager.get_fuel_logs(100)
    
    @app.get("/api/telemetry")
    async def get_telemetry():
        return DatabaseManager.get_telemetry(100)
    
    @app.post("/api/simulate_siphon/{truck_id}")
    async def simulate_siphon(truck_id: str):
        if truck_id in manager.trucks:
            amount = manager.trucks[truck_id].simulate_siphon()
            return {"success": True, "amount_liters": amount, "truck_id": truck_id}
        return {"success": False, "message": "Truck not found"}
    
    @app.get("/")
    @app.get("/dashboard")
    async def dashboard():
        return HTMLResponse("""
        <!DOCTYPE html>
        <html>
        <head>
            <title>FuelGuard Dashboard</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; background: linear-gradient(135deg, #1a472a 0%, #0d2818 100%); min-height: 100vh; }
                .container { max-width: 1200px; margin: 0 auto; }
                h1 { color: white; text-align: center; }
                h2 { color: #ff6b35; }
                .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f0f0f0; }
                .fuel-bar { background: #e0e0e0; border-radius: 10px; overflow: hidden; width: 100px; display: inline-block; }
                .fuel-fill { background: #ff6b35; height: 20px; }
                button { background: #dc2626; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
                .critical { color: #dc2626; font-weight: bold; }
                .warning { color: #f59e0b; }
                .normal { color: #10b981; }
                .refresh-time { text-align: right; color: white; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🚛 FuelGuard Fleet Dashboard</h1>
                <div class="refresh-time">Last updated: <span id="lastUpdate">-</span></div>
                
                <div class="card">
                    <h2>Active Trucks</h2>
                    <div id="trucks"></div>
                </div>
                
                <div class="card">
                    <h2>Recent Telemetry</h2>
                    <div id="telemetry"></div>
                </div>
                
                <div class="card">
                    <h2>Recent Alerts</h2>
                    <div id="alerts"></div>
                </div>
            </div>
            
            <script>
                async function loadData() {
                    document.getElementById('lastUpdate').innerText = new Date().toLocaleTimeString();
                    
                    const truckResponse = await fetch('/api/trucks');
                    const trucks = await truckResponse.json();
                    let truckHtml = '<tr><th>Truck ID</th><th>License Plate</th><th>Driver</th><th>Status</th><th>Action</th></tr>';
                    for (const truck of trucks) {
                        truckHtml += `<tr>
                            <td>${truck.truck_id || 'N/A'}</td>
                            <td>${truck.license_plate || 'N/A'}</td>
                            <td>${truck.driver_name || 'N/A'}</td>
                            <td><span class="normal">${truck.status || 'ACTIVE'}</span></td>
                            <td><button onclick="simulateSiphon('${truck.truck_id}')">⚠ Simulate Siphon</button></td>
                        </tr>`;
                    }
                    truckHtml += '</table>';
                    document.getElementById('trucks').innerHTML = truckHtml;
                    
                    const telemetryResponse = await fetch('/api/telemetry');
                    const telemetry = await telemetryResponse.json();
                    if (telemetry.length > 0) {
                        let telemetryHtml = '<table><th>Timestamp</th><th>License Plate</th><th>Driver</th><th>Fuel %</th><th>Fuel Volume</th><th>Status</th></tr>';
                        for (const record of telemetry.slice(0, 10)) {
                            let statusClass = record.status === 'CRITICAL' ? 'critical' : (record.status === 'WARNING' ? 'warning' : 'normal');
                            telemetryHtml += `<tr>
                                <td>${new Date(record.timestamp).toLocaleString()}</td>
                                <td>${record.license_plate || 'N/A'}</td>
                                <td>${record.driver_name || 'Unknown'}</td>
                                <td><div class="fuel-bar"><div class="fuel-fill" style="width: ${record.fuel_percentage || 0}%"></div></div> ${record.fuel_percentage || 0}%</td>
                                <td>${record.fuel_volume_liters || 0} L</div></td>
                                <td><span class="${statusClass}">${record.status || 'NORMAL'}</span></td>
                            </tr>`;
                        }
                        telemetryHtml += '</table>';
                        document.getElementById('telemetry').innerHTML = telemetryHtml;
                    } else {
                        document.getElementById('telemetry').innerHTML = '<p>No telemetry data available</p>';
                    }
                    
                    const alertResponse = await fetch('/api/alerts');
                    const alerts = await alertResponse.json();
                    if (alerts.length > 0) {
                        let alertHtml = '<table><th>Date</th><th>Truck</th><th>Type</th><th>Message</th><th>Status</th></tr>';
                        for (const alert of alerts.slice(0, 10)) {
                            let alertClass = alert.alert_type === 'CRITICAL_FUEL' ? 'critical' : 'warning';
                            alertHtml += `<tr>
                                <td>${new Date(alert.alert_date).toLocaleString()}</td>
                                <td>${alert.truck_id || 'N/A'}</td>
                                <td><span class="${alertClass}">${alert.alert_type || 'N/A'}</span></td>
                                <td>${(alert.alert_message || '').substring(0, 50)}</div></td>
                                <td>${alert.status || 'Pending'} ${alert.is_resolved ? '✓' : ''}</td>
                            </tr>`;
                        }
                        alertHtml += '</table>';
                        document.getElementById('alerts').innerHTML = alertHtml;
                    } else {
                        document.getElementById('alerts').innerHTML = '<p>No alerts found</p>';
                    }
                }
                
                async function simulateSiphon(truckId) {
                    if (confirm(`⚠️ WARNING: This will simulate a fuel siphon event on truck ${truckId}!`)) {
                        const response = await fetch(`/api/simulate_siphon/${truckId}`, { method: 'POST' });
                        const result = await response.json();
                        if (result.success) {
                            alert(`✅ Siphon simulated! ${result.amount_liters.toFixed(1)}L removed`);
                            loadData();
                        } else {
                            alert('❌ Failed to simulate siphon');
                        }
                    }
                }
                
                setInterval(loadData, 5000);
                loadData();
            </script>
        </body>
        </html>
        """)
    
    return app

# ============================================================================
# MAIN APPLICATION
# ============================================================================

def main():
    parser = argparse.ArgumentParser(description="FuelGuard Fleet System")
    parser.add_argument("--interval", type=int, default=5, help="Update interval in seconds")
    parser.add_argument("--web-port", type=int, default=8080, help="Web dashboard port")
    parser.add_argument("--no-web", action="store_true", help="Disable web dashboard")
    
    args = parser.parse_args()
    
    print("\n" + "="*60)
    print(" 🚛 FUELGUARD FLEET SYSTEM ")
    print("="*60)
    
    if SUPABASE_URL and SUPABASE_KEY:
        print(f"✅ Supabase Connected: {SUPABASE_URL}")
    else:
        print("❌ Supabase not configured!")
        print("\nPlease create a .env file with:")
        print("  SUPABASE_URL=your_supabase_url")
        print("  SUPABASE_KEY=your_supabase_anon_key")
        return
    
    manager = FleetManager()
    
    if not manager.load_fleet():
        return
    
    if FASTAPI_AVAILABLE and not args.no_web:
        try:
            app = create_fastapi_app(manager)
            
            def run_server():
                uvicorn.run(app, host="0.0.0.0", port=args.web_port, log_level="warning")
            
            server_thread = threading.Thread(target=run_server, daemon=True)
            server_thread.start()
            print(f"\n🌐 Web Dashboard: http://localhost:{args.web_port}/dashboard")
            print(f"   API Endpoints:")
            print(f"     - Trucks: http://localhost:{args.web_port}/api/trucks")
            print(f"     - Telemetry: http://localhost:{args.web_port}/api/telemetry")
            print(f"     - Alerts: http://localhost:{args.web_port}/api/alerts")
            webbrowser.open(f"http://localhost:{args.web_port}/dashboard")
        except Exception as e:
            print(f"⚠️ Web server error: {e}")
            print("   Continuing with console UI only...")
    
    print("\n" + "="*60)
    print(" 📊 SYSTEM READY")
    print("="*60)
    print("\n 💡 Features:")
    print("    - Fuel decreases by PERCENTAGE every 5 seconds")
    print("    - Current decrease rate: 0.5% per 5 seconds (6% per minute)")
    print("    - Telemetry stored in fuel_telemetry table every 5 seconds")
    print("    - Low fuel alert at 20% (saved to alerts table)")
    print("    - Critical fuel alert at 10% (saved to alerts table)")
    print("    - Siphon removes 15-30% of current fuel")
    print("\n 💡 Commands:")
    print("    s1, s2, s3 - Simulate siphon on truck")
    print("    r           - Refresh display")
    print("    q           - Quit")
    print("\n 💡 To adjust fuel decrease speed, modify 'fuel_decrease_percent_per_cycle'")
    print("    in the TruckFuelSimulator __init__ method (line ~220)")
    print("="*60 + "\n")
    
    try:
        manager.run_console_ui(args.interval)
    except KeyboardInterrupt:
        print("\n\n👋 Shutting down...")
    
    print("\n✅ System stopped")

if __name__ == "__main__":
    main()