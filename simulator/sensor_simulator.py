# simulator/sensor_simulator.py - Database Integrated Fleet Simulator
import time
import random
import requests
from datetime import datetime
import argparse
import threading
import os
import sys
from typing import Optional, Dict, List

# Add parent directory to path to load .env
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from dotenv import load_dotenv

# Load environment from gateway folder
env_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'gateway', '.env')
load_dotenv(env_path)

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_KEY")

class DatabaseManager:
    """Manages database operations for fleet data"""
    
    @staticmethod
    def get_trucks_from_db():
        """Fetch all trucks from Supabase"""
        if not SUPABASE_URL or not SUPABASE_KEY:
            print("⚠️ Supabase not configured. Please check gateway/.env")
            return []
        try:
            url = f"{SUPABASE_URL}/rest/v1/trucks"
            headers = {
                "apikey": SUPABASE_KEY,
                "Authorization": f"Bearer {SUPABASE_KEY}"
            }
            response = requests.get(url, headers=headers, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching trucks: {e}")
            return []
    
    @staticmethod
    def get_sensors_from_db():
        """Fetch all sensors from Supabase"""
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        try:
            url = f"{SUPABASE_URL}/rest/v1/sensors"
            headers = {
                "apikey": SUPABASE_KEY,
                "Authorization": f"Bearer {SUPABASE_KEY}"
            }
            response = requests.get(url, headers=headers, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching sensors: {e}")
            return []
    
    @staticmethod
    def get_assignments_from_db():
        """Fetch active sensor assignments from Supabase"""
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        try:
            url = f"{SUPABASE_URL}/rest/v1/sensor_assignments"
            headers = {
                "apikey": SUPABASE_KEY,
                "Authorization": f"Bearer {SUPABASE_KEY}"
            }
            params = {
                "is_active": "eq.true"
            }
            response = requests.get(url, headers=headers, params=params, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching assignments: {e}")
            return []
    
    @staticmethod
    def get_latest_telemetry():
        """Get latest telemetry for all trucks from the view"""
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        try:
            url = f"{SUPABASE_URL}/rest/v1/latest_telemetry"
            headers = {
                "apikey": SUPABASE_KEY,
                "Authorization": f"Bearer {SUPABASE_KEY}"
            }
            response = requests.get(url, headers=headers, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching latest telemetry: {e}")
            return []
    
    @staticmethod
    def get_trucks_low_fuel():
        """Get trucks with low fuel from view"""
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        try:
            url = f"{SUPABASE_URL}/rest/v1/trucks_low_fuel"
            headers = {
                "apikey": SUPABASE_KEY,
                "Authorization": f"Bearer {SUPABASE_KEY}"
            }
            response = requests.get(url, headers=headers, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching low fuel trucks: {e}")
            return []
    
    @staticmethod
    def get_available_sensors():
        """Get available sensors from view"""
        if not SUPABASE_URL or not SUPABASE_KEY:
            return []
        try:
            url = f"{SUPABASE_URL}/rest/v1/available_sensors"
            headers = {
                "apikey": SUPABASE_KEY,
                "Authorization": f"Bearer {SUPABASE_KEY}"
            }
            response = requests.get(url, headers=headers, timeout=10)
            if response.status_code == 200:
                return response.json()
            return []
        except Exception as e:
            print(f"❌ Error fetching available sensors: {e}")
            return []

class SensorSimulator:
    """Simulates individual sensor readings"""
    
    def __init__(self, sensor: dict, truck: dict, api_url: str):
        self.sensor = sensor
        self.truck = truck
        self.api_url = api_url.rstrip('/')
        
        # Get tank parameters from truck
        self.tank_height_cm = truck.get('tank_height_cm', 120.0)
        self.tank_capacity_liters = truck.get('tank_capacity_liters', 300.0)
        self.current_fuel_level_cm = self.tank_height_cm
        self.current_fuel_liters = self.tank_capacity_liters
        
        # Simulation variables
        self.consumption_rate_lph = random.uniform(5, 15)
        self.tank_cross_section_m2 = 2.5
        self.temperature = random.uniform(20, 35)
        
    def create_compact_fuel_bar(self, percentage: float) -> str:
        bar_length = 10
        filled = int(bar_length * percentage / 100)
        empty = bar_length - filled
        
        if percentage >= 75:
            bar_char = "█"
        elif percentage >= 50:
            bar_char = "▓"
        elif percentage >= 25:
            bar_char = "▒"
        else:
            bar_char = "░"
        
        return bar_char * filled + "░" * empty
    
    def calculate_reading(self) -> float:
        consumption_cm_per_sec = (self.consumption_rate_lph / 3600) / self.tank_cross_section_m2 / 100
        
        self.current_fuel_level_cm -= consumption_cm_per_sec
        self.current_fuel_level_cm = max(0, self.current_fuel_level_cm)
        
        distance = self.tank_height_cm - self.current_fuel_level_cm
        noise = random.gauss(0, 0.5)
        distance += noise
        
        self.current_fuel_liters = (self.current_fuel_level_cm / self.tank_height_cm) * self.tank_capacity_liters
        
        return max(0, min(distance, self.tank_height_cm))
    
    def send_telemetry(self, distance_cm: float):
        fuel_level_cm = self.current_fuel_level_cm
        fuel_percentage = (fuel_level_cm / self.tank_height_cm) * 100
        
        self.temperature += random.uniform(-0.5, 0.5)
        self.temperature = max(15, min(45, self.temperature))
        
        telemetry = {
            "device_id": self.sensor.get('sensor_id'),
            "sensor_type": self.sensor.get('sensor_type', 'ultrasonic'),
            "raw_value": round(distance_cm, 2),
            "temperature": round(self.temperature, 1),
            "timestamp": datetime.now().isoformat(),
            "truck_id": self.truck.get('truck_id'),
            "license_plate": self.truck.get('license_plate'),
            "driver_name": self.truck.get('driver_name'),
            "fuel_level_cm": round(fuel_level_cm, 2),
            "fuel_percentage": round(fuel_percentage, 2),
            "fuel_volume_liters": round(self.current_fuel_liters, 2),
            "tank_capacity_liters": self.tank_capacity_liters
        }
        
        try:
            response = requests.post(
                f"{self.api_url}/api/logs",
                json=telemetry,
                timeout=5
            )
            
            if response.status_code == 200:
                fuel_bar = self.create_compact_fuel_bar(fuel_percentage)
                
                if fuel_percentage >= 75:
                    status_emoji = "🟢"
                elif fuel_percentage >= 50:
                    status_emoji = "🟡"
                elif fuel_percentage >= 25:
                    status_emoji = "🟠"
                else:
                    status_emoji = "🔴"
                
                print(f"{status_emoji} {self.truck.get('license_plate', 'N/A'):10} | "
                      f"{self.truck.get('driver_name', 'N/A')[:15]:15} | "
                      f"{fuel_bar} | "
                      f"{fuel_percentage:5.1f}% | "
                      f"{self.current_fuel_liters:5.0f}L | "
                      f"{self.temperature:4.1f}°C")
                return True
            return False
        except Exception as e:
            return False

class FleetManager:
    """Manages all sensors from database"""
    
    def __init__(self, api_url: str = "http://localhost:8000"):
        self.api_url = api_url
        self.db = DatabaseManager()
        self.running = True
        
    def load_and_display_fleet(self):
        """Load and display fleet data from database"""
        print("\n📡 Loading fleet data from Supabase...")
        
        # Load data
        trucks = self.db.get_trucks_from_db()
        sensors = self.db.get_sensors_from_db()
        assignments = self.db.get_assignments_from_db()
        
        print(f"   ✅ Loaded {len(trucks)} trucks")
        print(f"   ✅ Loaded {len(sensors)} sensors")
        print(f"   ✅ Loaded {len(assignments)} active assignments")
        
        # Display summary
        print("\n" + "="*70)
        print("📊 CURRENT FLEET STATUS FROM DATABASE")
        print("="*70)
        
        print("\n🚛 TRUCKS:")
        for truck in trucks:
            print(f"   {truck.get('truck_id', 'N/A'):8} | {truck.get('license_plate', 'N/A'):10} | {truck.get('driver_name', 'N/A'):15} | {truck.get('tank_capacity_liters', 0)}L")
        
        print("\n🔌 SENSORS:")
        for sensor in sensors:
            print(f"   {sensor.get('sensor_id', 'N/A'):12} | {sensor.get('sensor_type', 'N/A'):12} | Status: {sensor.get('status', 'UNKNOWN')}")
        
        print("\n🔗 ACTIVE ASSIGNMENTS:")
        for assign in assignments:
            print(f"   {assign.get('sensor_id')} → {assign.get('truck_id')}")
        
        print("="*70)
        
        return trucks, sensors, assignments
    
    def get_active_sensors_with_trucks(self):
        """Get list of active sensors with their assigned trucks"""
        trucks = self.db.get_trucks_from_db()
        sensors = self.db.get_sensors_from_db()
        assignments = self.db.get_assignments_from_db()
        
        # Create truck lookup dictionary
        truck_dict = {t.get('truck_id'): t for t in trucks}
        
        # Create assignment map
        assignment_map = {a.get('sensor_id'): a.get('truck_id') for a in assignments}
        
        active = []
        for sensor in sensors:
            sensor_id = sensor.get('sensor_id')
            if sensor_id in assignment_map:
                truck_id = assignment_map[sensor_id]
                truck = truck_dict.get(truck_id)
                if truck:
                    active.append({
                        'sensor': sensor,
                        'truck': truck
                    })
        return active
    
    def start_all_sensors(self, interval: int = 5):
        """Start all assigned sensors from database"""
        active = self.get_active_sensors_with_trucks()
        
        if not active:
            print("\n❌ No active sensor assignments found in database!")
            print("\n📋 Please create assignments in Supabase:")
            print("   1. Go to Supabase Table Editor")
            print("   2. Add records to 'sensor_assignments' table")
            print("   3. Set sensor status to 'ASSIGNED' in 'sensors' table")
            return
        
        # Clear screen
        os.system('cls' if os.name == 'nt' else 'clear')
        
        # Print header
        print("\n" + "="*95)
        print(" 🚛 FUELGUARD FLEET MONITORING DASHBOARD ")
        print("="*95)
        print(f" 📊 Active Trucks: {len(active)} | 🔌 Active Sensors: {len(active)} | ⏱️  Interval: {interval}s")
        print("="*95)
        print(f"{'':<2} {'TRUCK':<12} {'DRIVER':<17} {'FUEL GAUGE':<14} {'%':<7} {'VOLUME':<8} {'TEMP':<6}")
        print("-"*95)
        
        # Start each sensor
        for item in active:
            simulator = SensorSimulator(item['sensor'], item['truck'], self.api_url)
            thread = threading.Thread(target=self._run_simulator, args=(simulator, interval), daemon=True)
            thread.start()
            time.sleep(0.3)
        
        print("\n" + "="*95)
        print(" ✅ System running | Press Ctrl+C to stop")
        print("="*95 + "\n")
        
        try:
            while self.running:
                time.sleep(1)
        except KeyboardInterrupt:
            print("\n\n👋 Stopping all sensors...")
            self.running = False
    
    def _run_simulator(self, simulator, interval):
        """Run a single simulator instance"""
        while self.running:
            distance = simulator.calculate_reading()
            simulator.send_telemetry(distance)
            time.sleep(interval)
    
    def show_database_status(self):
        """Display current database status"""
        print("\n" + "="*60)
        print(" 📊 DATABASE STATUS REPORT ")
        print("="*60)
        
        # Show latest telemetry
        latest = self.db.get_latest_telemetry()
        if latest:
            print("\n📡 LATEST TELEMETRY FROM DATABASE:")
            print("-" * 60)
            for record in latest:
                print(f"   {record.get('truck_id', 'N/A'):8} | "
                      f"{record.get('fuel_percentage', 0):5.1f}% | "
                      f"Status: {record.get('status', 'N/A')}")
        
        # Show low fuel trucks
        low_fuel = self.db.get_trucks_low_fuel()
        if low_fuel:
            print("\n⚠️ TRUCKS WITH LOW FUEL (<25%):")
            print("-" * 60)
            for truck in low_fuel:
                print(f"   {truck.get('truck_id', 'N/A')} | {truck.get('fuel_percentage', 0):.1f}%")
        
        # Show available sensors
        available = self.db.get_available_sensors()
        if available:
            print("\n🆓 AVAILABLE SENSORS:")
            print("-" * 60)
            for sensor in available:
                print(f"   {sensor.get('sensor_id', 'N/A')} ({sensor.get('sensor_type', 'N/A')})")

def main():
    parser = argparse.ArgumentParser(description="Database Integrated Fleet Sensor System")
    parser.add_argument("--api-url", default="http://localhost:8000", help="API URL")
    parser.add_argument("--interval", type=int, default=5, help="Reading interval in seconds")
    
    args = parser.parse_args()
    
    print("\n" + "="*60)
    print(" 🚛 FUELGUARD DATABASE INTEGRATED SIMULATOR ")
    print("="*60)
    
    manager = FleetManager(args.api_url)
    
    # Load and display fleet data
    manager.load_and_display_fleet()
    
    # Show database status
    manager.show_database_status()
    
    # Start monitoring
    print("\n" + "="*60)
    input("Press Enter to start monitoring all assigned sensors...")
    
    manager.start_all_sensors(args.interval)

if __name__ == "__main__":
    main()