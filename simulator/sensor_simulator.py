# simulator/sensor_simulator.py - Database Integrated Fleet Simulator with Location Tracking
import time
import random
import requests
from datetime import datetime
import argparse
import threading
import os
import sys
import math
from typing import Optional, Dict, List, Tuple

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

class TruckLocation:
    """Manages truck location and route simulation"""
    
    # Define some routes (start and end points)
    ROUTES = {
        "ROUTE_1": {
            "name": "City Center Route",
            "start": {"lat": 40.7128, "lng": -74.0060, "name": "Warehouse A"},
            "end": {"lat": 40.7580, "lng": -73.9855, "name": "City Center"},
            "distance_km": 12.5
        },
        "ROUTE_2": {
            "name": "Industrial Route",
            "start": {"lat": 40.7580, "lng": -73.9855, "name": "City Center"},
            "end": {"lat": 40.7489, "lng": -73.9680, "name": "Industrial Park"},
            "distance_km": 8.3
        },
        "ROUTE_3": {
            "name": "Port Route",
            "start": {"lat": 40.7128, "lng": -74.0060, "name": "Warehouse A"},
            "end": {"lat": 40.7000, "lng": -74.0500, "name": "Port Terminal"},
            "distance_km": 15.7
        },
        "ROUTE_4": {
            "name": "Suburban Route",
            "start": {"lat": 40.7580, "lng": -73.9855, "name": "City Center"},
            "end": {"lat": 40.8000, "lng": -73.9500, "name": "Suburb Depot"},
            "distance_km": 10.2
        },
        "ROUTE_5": {
            "name": "Airport Route",
            "start": {"lat": 40.7128, "lng": -74.0060, "name": "Warehouse A"},
            "end": {"lat": 40.6413, "lng": -73.7781, "name": "Airport Cargo"},
            "distance_km": 22.3
        }
    }
    
    def __init__(self, truck_id: str):
        self.truck_id = truck_id
        self.current_route = None
        self.start_point = None
        self.end_point = None
        self.total_distance_km = 0
        self.distance_traveled_km = 0
        self.current_position = None
        self.speed_kmh = random.uniform(40, 80)  # Speed between 40-80 km/h
        self.is_moving = True
        self.engine_running = False  # Track engine state
        
        # Select random route for this truck
        self.assign_route()
        
    def start_engine(self):
        """Start the truck engine"""
        if not self.engine_running:
            self.engine_running = True
            self.is_moving = True
            return True
        return False
    
    def stop_engine(self):
        """Stop the truck engine"""
        if self.engine_running:
            self.engine_running = False
            self.is_moving = False
            return True
        return False
    
    def assign_route(self):
        """Assign a random route to the truck"""
        route_key = random.choice(list(self.ROUTES.keys()))
        self.current_route = self.ROUTES[route_key]
        self.start_point = self.current_route["start"]
        self.end_point = self.current_route["end"]
        self.total_distance_km = self.current_route["distance_km"]
        self.distance_traveled_km = 0
        self.current_position = self.start_point.copy()
        
    def update_position(self, elapsed_seconds: float) -> Tuple[float, float, float]:
        """
        Update truck position based on elapsed time
        Returns (lat, lng, distance_traveled_km)
        """
        if not self.is_moving or not self.engine_running:
            return self.current_position["lat"], self.current_position["lng"], 0
        
        # Calculate distance traveled in this interval
        distance_traveled = (self.speed_kmh / 3600) * elapsed_seconds
        self.distance_traveled_km += distance_traveled
        
        # Check if reached destination
        if self.distance_traveled_km >= self.total_distance_km:
            # Reached destination, either stop or start new route
            if random.random() < 0.3:  # 30% chance to stop
                self.is_moving = False
                self.distance_traveled_km = self.total_distance_km
            else:  # Start new route
                self.assign_route()
                # Continue from current position (now becomes new start)
                self.start_point = self.current_position.copy()
                self.distance_traveled_km = 0
            
            return self.current_position["lat"], self.current_position["lng"], self.total_distance_km
        
        # Calculate progress along route (0 to 1)
        progress = self.distance_traveled_km / self.total_distance_km
        
        # Interpolate position
        lat = self.start_point["lat"] + (self.end_point["lat"] - self.start_point["lat"]) * progress
        lng = self.start_point["lng"] + (self.end_point["lng"] - self.start_point["lng"]) * progress
        
        self.current_position = {"lat": lat, "lng": lng}
        
        return lat, lng, distance_traveled
    
    def get_location_name(self) -> str:
        """Get current location name based on progress"""
        if not self.engine_running:
            return f"PARKED at {self.current_position.get('name', 'Unknown') if self.current_position else 'Unknown'}"
        elif self.distance_traveled_km <= 0:
            return self.start_point["name"]
        elif self.distance_traveled_km >= self.total_distance_km:
            return self.end_point["name"]
        else:
            return f"En route ({self.distance_traveled_km:.1f}/{self.total_distance_km:.1f} km)"
    
    def get_route_info(self) -> dict:
        """Get current route information"""
        return {
            "route": self.current_route["name"] if self.current_route else "No route",
            "from": self.start_point["name"] if self.start_point else "Unknown",
            "to": self.end_point["name"] if self.end_point else "Unknown",
            "progress_percent": (self.distance_traveled_km / self.total_distance_km * 100) if self.total_distance_km > 0 else 0,
            "distance_remaining_km": max(0, self.total_distance_km - self.distance_traveled_km),
            "distance_traveled_km": self.distance_traveled_km,
            "speed_kmh": self.speed_kmh if self.engine_running else 0,
            "is_moving": self.is_moving and self.engine_running,
            "engine_running": self.engine_running
        }

class SensorSimulator:
    """Simulates individual sensor readings with location-based fuel consumption"""
    
    def __init__(self, sensor: dict, truck: dict, api_url: str):
        self.sensor = sensor
        self.truck = truck
        self.api_url = api_url.rstrip('/')
        
        # Get tank parameters from truck
        self.tank_height_cm = truck.get('tank_height_cm', 120.0)
        self.tank_capacity_liters = truck.get('tank_capacity_liters', 300.0)
        self.current_fuel_level_cm = self.tank_height_cm
        self.current_fuel_liters = self.tank_capacity_liters
        self.current_fuel_percentage = 100.0
        
        # Fixed fuel consumption rate: 0.5% every 5 seconds (as requested)
        self.fuel_decrease_percent_per_second = 0.1  # 0.5% per 5 seconds = 0.1% per second
        
        # Simulation variables
        self.last_update_time = time.time()
        
        # Location tracking
        truck_id = truck.get('truck_id', 'UNKNOWN')
        self.location = TruckLocation(truck_id)
        
        # Total odometer
        self.total_odometer_km = random.uniform(10000, 50000)
        
    def create_compact_fuel_bar(self, percentage: float) -> str:
        bar_length = 20  # Made longer for better visualization
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
    
    def update_fuel_level(self, elapsed_seconds: float):
        """Update fuel level based on elapsed time (0.5% per 5 seconds)"""
        if elapsed_seconds <= 0:
            return
        
        # Calculate fuel decrease percentage
        fuel_decrease_percent = self.fuel_decrease_percent_per_second * elapsed_seconds
        
        # Update fuel percentage
        self.current_fuel_percentage = max(0, self.current_fuel_percentage - fuel_decrease_percent)
        
        # Update fuel volume and level
        self.current_fuel_liters = (self.current_fuel_percentage / 100) * self.tank_capacity_liters
        self.current_fuel_level_cm = (self.current_fuel_percentage / 100) * self.tank_height_cm
        
    def generate_reading(self) -> Tuple[float, dict]:
        """
        Generate a new reading based on elapsed time
        Returns (distance_cm, location_info)
        """
        current_time = time.time()
        elapsed_seconds = current_time - self.last_update_time
        self.last_update_time = current_time
        
        # Limit elapsed time to avoid huge jumps
        if elapsed_seconds > 30:
            elapsed_seconds = 5
        
        # Update position and get distance traveled
        lat, lng, distance_traveled_km = self.location.update_position(elapsed_seconds)
        
        # Update fuel based on elapsed time (always decreases regardless of movement)
        self.update_fuel_level(elapsed_seconds)
        
        # Update odometer only when moving
        if distance_traveled_km > 0:
            self.total_odometer_km += distance_traveled_km
        
        # Generate ultrasonic sensor reading (distance from top of tank)
        distance_cm = self.tank_height_cm - self.current_fuel_level_cm
        
        # Add noise to sensor reading
        noise = random.gauss(0, 0.3)
        distance_cm += noise
        distance_cm = max(0, min(distance_cm, self.tank_height_cm))
        
        # Prepare location info
        location_info = {
            "latitude": lat,
            "longitude": lng,
            "location_name": self.location.get_location_name(),
            "route_info": self.location.get_route_info(),
            "odometer_km": round(self.total_odometer_km, 1),
            "trip_distance_km": round(self.location.distance_traveled_km, 1),
            "speed_kmh": round(self.location.speed_kmh if self.location.engine_running else 0, 1),
            "engine_running": self.location.engine_running
        }
        
        return distance_cm, location_info
    
    def start_engine(self):
        """Start the truck engine"""
        return self.location.start_engine()
    
    def stop_engine(self):
        """Stop the truck engine"""
        return self.location.stop_engine()
    
    def send_telemetry(self):
        """Send telemetry data to API"""
        distance_cm, location_info = self.generate_reading()
        
        fuel_level_cm = self.tank_height_cm - distance_cm
        fuel_percentage = (fuel_level_cm / self.tank_height_cm) * 100
        fuel_volume = (fuel_percentage / 100) * self.tank_capacity_liters
        
        # Determine status
        if fuel_percentage >= 75:
            status = "NORMAL"
            status_emoji = "🟢"
        elif fuel_percentage >= 50:
            status = "NORMAL"
            status_emoji = "🟡"
        elif fuel_percentage >= 25:
            status = "WARNING"
            status_emoji = "🟠"
        elif fuel_percentage >= 10:
            status = "LOW"
            status_emoji = "🔴"
        else:
            status = "CRITICAL"
            status_emoji = "💀"
        
        # Engine status display
        engine_icon = "🔑" if location_info["engine_running"] else "🔒"
        engine_text = "RUNNING" if location_info["engine_running"] else "PARKED"
        
        # Speed display
        speed_display = f"{location_info['speed_kmh']:.0f}km/h" if location_info['speed_kmh'] > 0 else "PARKED"
        
        telemetry = {
            "device_id": self.sensor.get('sensor_id'),
            "sensor_type": self.sensor.get('sensor_type', 'ultrasonic'),
            "raw_value_cm": round(distance_cm, 2),
            "timestamp": datetime.now().isoformat(),
            "truck_id": self.truck.get('truck_id'),
            "license_plate": self.truck.get('license_plate'),
            "driver_name": self.truck.get('driver_name'),
            "fuel_level_cm": round(fuel_level_cm, 2),
            "fuel_percentage": round(fuel_percentage, 2),
            "fuel_volume_liters": round(fuel_volume, 2),
            "tank_capacity_liters": self.tank_capacity_liters,
            "status": status,
            # Location data
            "latitude": location_info["latitude"],
            "longitude": location_info["longitude"],
            "location_name": location_info["location_name"],
            "odometer_km": location_info["odometer_km"],
            "speed_kmh": location_info["speed_kmh"],
            "trip_distance_km": location_info["trip_distance_km"],
            "engine_running": location_info["engine_running"]
        }
        
        try:
            response = requests.post(
                f"{self.api_url}/api/logs",
                json=telemetry,
                timeout=5
            )
            
            if response.status_code == 200:
                fuel_bar = self.create_compact_fuel_bar(fuel_percentage)
                
                # Display in your requested format
                print(f"   {self.truck.get('license_plate', 'N/A'):12} | "
                      f"{self.truck.get('driver_name', 'N/A')[:15]:15} | "
                      f"{status_emoji} {fuel_bar} | "
                      f"{fuel_percentage:5.1f}% | "
                      f"{fuel_volume:5.0f}L | "
                      f"{location_info['trip_distance_km']:7.1f}km | "
                      f"{status:8} | "
                      f"{speed_display:10} | "
                      f"{engine_text:8}")
                
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
        self.simulators = []  # Track all simulator instances
        
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
            capacity = truck.get('tank_capacity_liters', 0)
            height = truck.get('tank_height_cm', 0)
            consumption = truck.get('fuel_consumption_l_per_km', 'N/A')
            print(f"   {truck.get('truck_id', 'N/A'):8} | "
                  f"{truck.get('license_plate', 'N/A'):10} | "
                  f"{truck.get('driver_name', 'N/A'):15} | "
                  f"{capacity}L | {height}cm")
        
        print("\n🔌 SENSORS:")
        for sensor in sensors:
            print(f"   {sensor.get('sensor_id', 'N/A'):12} | "
                  f"{sensor.get('sensor_type', 'N/A'):12} | "
                  f"Status: {sensor.get('status', 'UNKNOWN')}")
        
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
    
    def handle_engine_commands(self):
        """Handle keyboard input for engine start/stop commands"""
        while self.running:
            try:
                cmd = input().strip().lower()
                if cmd == 'start':
                    print("\n🔑 Starting all engines...")
                    for sim in self.simulators:
                        if not sim.location.engine_running:
                            sim.start_engine()
                            print(f"   ✅ {sim.truck.get('license_plate', 'Unknown')} engine started")
                elif cmd == 'stop':
                    print("\n🔒 Stopping all engines...")
                    for sim in self.simulators:
                        if sim.location.engine_running:
                            sim.stop_engine()
                            print(f"   ✅ {sim.truck.get('license_plate', 'Unknown')} engine stopped")
                elif cmd == 'quit' or cmd == 'exit':
                    self.running = False
                    break
            except EOFError:
                break
            except Exception:
                pass
    
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
        
        # Print header in your requested format
        print("\n" + "="*130)
        print(" 🗄️  Database: Supabase (trucks, fuel_telemetry, alerts)")
        print(f" ⛽ Fuel decrease: 0.5% per {interval} seconds")
        print("="*130)
        print()
        print(f"   {'LICENSE PLATE':<12} {'DRIVER':<17} {'FUEL GAUGE':<24} {'%':<7} {'VOL':<7} {'DISTANCE':<9} {'STATUS':<8} {'SPEED':<10} {'ENGINE'}")
        print("-"*130)
        
        # Start each sensor
        for item in active:
            simulator = SensorSimulator(item['sensor'], item['truck'], self.api_url)
            self.simulators.append(simulator)
            thread = threading.Thread(target=self._run_simulator, args=(simulator, interval), daemon=True)
            thread.start()
            time.sleep(0.3)
        
        print("\n" + "="*130)
        print(" 💡 COMMANDS: Type 'start' to start engines | Type 'stop' to stop engines | Type 'quit' to exit")
        print("="*130 + "\n")
        
        # Start command listener thread
        cmd_thread = threading.Thread(target=self.handle_engine_commands, daemon=True)
        cmd_thread.start()
        
        try:
            while self.running:
                time.sleep(1)
        except KeyboardInterrupt:
            print("\n\n👋 Stopping all sensors...")
            self.running = False
    
    def _run_simulator(self, simulator, interval):
        """Run a single simulator instance"""
        while self.running:
            simulator.send_telemetry()
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
                engine_status = "🏃" if record.get('engine_running', False) else "🅿️"
                print(f"   {record.get('truck_id', 'N/A'):8} | "
                      f"{record.get('fuel_percentage', 0):5.1f}% | "
                      f"Status: {record.get('status', 'N/A')} | "
                      f"{engine_status} {record.get('location_name', 'Unknown')[:20]}")
        
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
    parser = argparse.ArgumentParser(description="Database Integrated Fleet Sensor System with Location Tracking")
    parser.add_argument("--api-url", default="http://localhost:8000", help="API URL")
    parser.add_argument("--interval", type=int, default=5, help="Reading interval in seconds")
    
    args = parser.parse_args()
    
    print("\n" + "="*60)
    print(" 🚛 FUELGUARD DATABASE INTEGRATED SIMULATOR ")
    print(" WITH LOCATION TRACKING & TIME-BASED FUEL")
    print("="*60)
    
    manager = FleetManager(args.api_url)
    
    # Load and display fleet data
    manager.load_and_display_fleet()
    
    # Show database status
    manager.show_database_status()
    
    # Start monitoring
    print("\n" + "="*60)
    print("📍 How it works:")
    print(f"   - Fuel decreases by 0.5% every {args.interval} seconds")
    print("   - Type 'start' to start engines (trucks will move)")
    print("   - Type 'stop' to stop engines (trucks will park)")
    print("   - Speed: 40-80 km/h when moving")
    print("="*60)
    input("\nPress Enter to start monitoring all assigned sensors...")
    
    manager.start_all_sensors(args.interval)

if __name__ == "__main__":
    main()