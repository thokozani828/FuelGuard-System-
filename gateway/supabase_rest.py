# gateway/supabase_rest.py
import os
import requests
from datetime import datetime, timedelta
from dotenv import load_dotenv
import logging
import random
import time
from typing import List, Dict, Optional

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Load environment variables
load_dotenv()

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_KEY")

# Check if credentials are available
if SUPABASE_URL and SUPABASE_KEY:
    print("✅ Supabase credentials loaded")
    SUPABASE_AVAILABLE = True
else:
    print("⚠️ Supabase credentials not found. Data will not be saved to cloud.")
    SUPABASE_AVAILABLE = False

def create_fuel_bar(percentage: float, width: int = 20) -> str:
    """Create a visual fuel bar"""
    filled = int(width * percentage / 100)
    empty = width - filled
    
    if percentage >= 75:
        bar_char = "█"
    elif percentage >= 50:
        bar_char = "▓"
    elif percentage >= 25:
        bar_char = "▒"
    else:
        bar_char = "░"
    
    bar = bar_char * filled + "░" * empty
    return f"[{bar}]"

def get_status_emoji(percentage: float) -> str:
    """Get emoji based on fuel percentage"""
    if percentage >= 75:
        return "🟢"
    elif percentage >= 50:
        return "🟡"
    elif percentage >= 25:
        return "🟠"
    elif percentage >= 10:
        return "🔴"
    else:
        return "💀"

def format_timestamp(timestamp_str: str) -> str:
    """Format timestamp for display"""
    try:
        dt = datetime.fromisoformat(timestamp_str.replace('Z', '+00:00'))
        return dt.strftime("%Y-%m-%d %H:%M:%S")
    except:
        return timestamp_str[:19]

def display_telemetry_record(record: dict, title: str = "📡 TELEMETRY DATA"):
    """Display a single telemetry record in a formatted box"""
    print("\n" + "="*90)
    print(f" {title} ")
    print("="*90)
    
    device_id = record.get('device_id', 'N/A')
    truck_id = record.get('truck_id', 'N/A')
    license_plate = record.get('license_plate', 'N/A')
    driver_name = record.get('driver_name', 'N/A')
    
    fuel_pct = record.get('fuel_percentage', 0)
    fuel_volume = record.get('fuel_volume_liters', 0)
    fuel_level = record.get('fuel_level_cm', 0)
    
    status = record.get('status', 'UNKNOWN')
    temperature = record.get('temperature_c', 'N/A')
    timestamp = record.get('timestamp', datetime.now().isoformat())
    
    # Location info
    location_name = record.get('location_name', 'N/A')
    speed = record.get('speed_kmh', 'N/A')
    odometer = record.get('odometer_km', 'N/A')
    
    fuel_bar = create_fuel_bar(fuel_pct)
    status_emoji = get_status_emoji(fuel_pct)
    
    print(f"""
┌─────────────────────────────────────────────────────────────────────┐
│ 📊 DEVICE INFORMATION                                               │
├─────────────────────────────────────────────────────────────────────┤
│  📡 Sensor ID     : {device_id:<50} │
│  🚛 Truck ID      : {truck_id:<50} │
│  📋 License Plate : {license_plate:<50} │
│  👤 Driver Name   : {driver_name:<50} │
├─────────────────────────────────────────────────────────────────────┤
│ ⛽ FUEL STATUS                                                      │
├─────────────────────────────────────────────────────────────────────┤
│  {status_emoji} Percentage     : {fuel_pct:>6.1f}%                                      │
│  💧 Volume         : {fuel_volume:>8.1f} / {record.get('tank_capacity_liters', 'N/A'):>8} Liters          │
│  📏 Level          : {fuel_level:>6.1f} cm                                    │
│  📊 Fuel Gauge     : {fuel_bar} {fuel_pct:>5.1f}%                      │
│  🏷️  Status        : {status:<50} │
├─────────────────────────────────────────────────────────────────────┤
│ 📍 LOCATION & MOVEMENT                                              │
├─────────────────────────────────────────────────────────────────────┤
│  📍 Location       : {location_name:<50} │
│  🏁 Speed          : {speed} km/h{' ' * (44 - len(str(speed))) } │
│  📊 Odometer       : {odometer} km{' ' * (44 - len(str(odometer))) } │
├─────────────────────────────────────────────────────────────────────┤
│ 🌡️  ENVIRONMENT                                                    │
├─────────────────────────────────────────────────────────────────────┤
│  🌡️  Temperature    : {temperature:>6.1f}°C                                    │
│  🕐 Timestamp      : {format_timestamp(timestamp):<50} │
└─────────────────────────────────────────────────────────────────────┘
""")

def display_fleet_status(records: list):
    """Display fleet status for all trucks in a table format"""
    if not records:
        print("\n⚠️ No fleet data available")
        return
    
    print("\n" + "="*120)
    print(" 🚛 FLEET FUEL STATUS DASHBOARD ")
    print("="*120)
    print(f"📅 {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*120)
    
    # Header
    print(f"{'TRUCK':<8} {'PLATE':<12} {'DRIVER':<15} {'FUEL %':<8} {'VOLUME':<10} {'STATUS':<10} {'SPEED':<8} {'LOCATION'}")
    print("-"*120)
    
    # Display each truck
    for record in records:
        truck_id = record.get('truck_id', 'N/A')[:7]
        plate = record.get('license_plate', 'N/A')[:10]
        driver = record.get('driver_name', 'N/A')[:14]
        fuel_pct = record.get('fuel_percentage', 0)
        volume = record.get('fuel_volume_liters', 0)
        status = record.get('status', 'UNKNOWN')[:9]
        speed = record.get('speed_kmh', 0)
        location = record.get('location_name', 'Unknown')[:25]
        
        fuel_bar = create_fuel_bar(fuel_pct, 10)
        status_emoji = get_status_emoji(fuel_pct)
        
        # Color code based on fuel level
        if fuel_pct >= 75:
            color = "🟢"
        elif fuel_pct >= 50:
            color = "🟡"
        elif fuel_pct >= 25:
            color = "🟠"
        else:
            color = "🔴"
        
        speed_display = f"{speed:.0f}" if speed > 0 else "Stop"
        
        print(f"{color} {truck_id:<7} {plate:<12} {driver:<15} {fuel_pct:>6.1f}%  {volume:>8.1f}L   {status_emoji} {status:<9} {speed_display:>6}  {location[:25]}")
    
    print("="*120)
    
    # Summary statistics
    total_trucks = len(records)
    avg_fuel = sum(r.get('fuel_percentage', 0) for r in records) / total_trucks if total_trucks > 0 else 0
    critical_trucks = sum(1 for r in records if r.get('fuel_percentage', 0) < 15)
    low_trucks = sum(1 for r in records if 15 <= r.get('fuel_percentage', 0) < 30)
    moving_trucks = sum(1 for r in records if r.get('speed_kmh', 0) > 0)
    
    print(f"\n📊 SUMMARY:")
    print(f"   Total Trucks: {total_trucks}")
    print(f"   Moving Trucks: {moving_trucks}")
    print(f"   Average Fuel: {avg_fuel:.1f}%")
    print(f"   ⚠️  Critical (<15%): {critical_trucks}")
    print(f"   ⚠️  Low (15-30%): {low_trucks}")
    print("="*120)

def display_alerts(alerts: list):
    """Display alerts in a formatted way"""
    if not alerts:
        print("\n✅ No active alerts")
        return
    
    print("\n" + "="*80)
    print(" ⚠️  ACTIVE ALERTS ")
    print("="*80)
    
    for alert in alerts:
        alert_type = alert.get('alert_type', 'UNKNOWN')
        message = alert.get('message', 'No message')
        truck_id = alert.get('truck_id', 'N/A')
        created_at = alert.get('created_at', datetime.now().isoformat())
        
        # Choose emoji based on alert type
        if alert_type == 'CRITICAL':
            emoji = "🔴🔥"
        elif alert_type == 'LOW':
            emoji = "🟠⚠️"
        elif alert_type == 'EMPTY':
            emoji = "💀❌"
        else:
            emoji = "⚠️"
        
        print(f"""
┌─────────────────────────────────────────────────────────────────────┐
│ {emoji} ALERT: {alert_type:<63} │
├─────────────────────────────────────────────────────────────────────┤
│  📝 Message : {message:<63} │
│  🚛 Truck   : {truck_id:<63} │
│  🕐 Time    : {format_timestamp(created_at):<63} │
└─────────────────────────────────────────────────────────────────────┘
""")

def get_available_sensors() -> List[Dict]:
    """Get list of available sensors/trucks from the database"""
    if not SUPABASE_AVAILABLE:
        print("❌ Cannot fetch sensors: Supabase not available")
        return []
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}"
        }
        
        # Get distinct devices with their latest data
        params = {
            "select": "device_id,truck_id,license_plate,driver_name,fuel_percentage,status,location_name,speed_kmh",
            "order": "timestamp.desc",
            "limit": 1000
        }
        
        response = requests.get(url, headers=headers, params=params, timeout=5)
        
        if response.status_code == 200:
            # Get unique sensors
            sensors = {}
            for record in response.json():
                device_id = record.get('device_id')
                if device_id and device_id not in sensors:
                    sensors[device_id] = {
                        'device_id': device_id,
                        'truck_id': record.get('truck_id', 'N/A'),
                        'license_plate': record.get('license_plate', 'N/A'),
                        'driver_name': record.get('driver_name', 'N/A'),
                        'current_fuel': record.get('fuel_percentage', 100),
                        'status': record.get('status', 'UNKNOWN'),
                        'location': record.get('location_name', 'Unknown'),
                        'speed': record.get('speed_kmh', 0)
                    }
            
            sensors_list = list(sensors.values())
            print(f"📡 Found {len(sensors_list)} available sensors in database")
            return sensors_list
        else:
            print(f"❌ Failed to fetch sensors: {response.status_code}")
            return []
            
    except Exception as e:
        logger.error(f"Failed to get sensors: {e}")
        print(f"❌ Error fetching sensors: {e}")
        return []

def store_telemetry(data: dict):
    """Store telemetry data with location in Supabase using REST API"""
    if not SUPABASE_AVAILABLE:
        print("⏭️ Skipping Supabase storage (no credentials)")
        return False
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}",
            "Content-Type": "application/json",
            "Prefer": "return=minimal"
        }
        
        # Prepare data for insertion - INCLUDING ALL LOCATION FIELDS
        telemetry_record = {
            "device_id": data.get("device_id"),
            "sensor_type": data.get("sensor_type"),
            "raw_value_cm": data.get("raw_value_cm"),
            "temperature_c": data.get("temperature_c"),
            "fuel_level_cm": data.get("fuel_level_cm"),
            "fuel_percentage": data.get("fuel_percentage"),
            "fuel_volume_liters": data.get("fuel_volume_liters"),
            "status": data.get("status"),
            "truck_id": data.get("truck_id"),
            "driver_name": data.get("driver_name"),
            "license_plate": data.get("license_plate"),
            "tank_capacity_liters": data.get("tank_capacity_liters"),
            "timestamp": data.get("timestamp", datetime.utcnow().isoformat()),
            # LOCATION FIELDS
            "latitude": data.get("latitude"),
            "longitude": data.get("longitude"),
            "location_name": data.get("location_name"),
            "odometer_km": data.get("odometer_km"),
            "speed_kmh": data.get("speed_kmh"),
            "trip_distance_km": data.get("trip_distance_km")
        }
        
        # Remove None values
        telemetry_record = {k: v for k, v in telemetry_record.items() if v is not None}
        
        # Display what we're saving
        fuel_pct = telemetry_record.get('fuel_percentage', 0)
        device = telemetry_record.get('device_id', 'Unknown')
        truck = telemetry_record.get('truck_id', 'Unknown')
        location = telemetry_record.get('location_name', 'Unknown')
        speed = telemetry_record.get('speed_kmh', 0)
        
        location_info = f"📍 {location}"
        if speed > 0:
            location_info += f" @ {speed:.0f}km/h"
        
        print(f"\n💾 Saving to Supabase:")
        print(f"   📡 Sensor: {device}")
        print(f"   🚛 Truck: {truck}")
        print(f"   ⛽ Fuel: {fuel_pct:.1f}% {create_fuel_bar(fuel_pct)}")
        print(f"   {location_info}")
        
        response = requests.post(url, json=telemetry_record, headers=headers, timeout=5)
        
        if response.status_code == 201:
            print("   ✅ Saved successfully!")
            logger.info(f"✅ Stored telemetry for {data.get('device_id')}")
            return True
        else:
            print(f"   ❌ Failed: {response.status_code}")
            if response.text:
                print(f"   Details: {response.text[:200]}")
            return False
            
    except Exception as e:
        print(f"❌ Failed to save to Supabase: {e}")
        logger.error(f"Failed to store telemetry: {e}")
        return False

def get_latest_reading(device_id: str, display: bool = False):
    """Get latest reading for a device from Supabase"""
    if not SUPABASE_AVAILABLE:
        return None
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}"
        }
        params = {
            "select": "*",
            "device_id": f"eq.{device_id}",
            "order": "timestamp.desc",
            "limit": 1
        }
        
        response = requests.get(url, headers=headers, params=params, timeout=5)
        
        if response.status_code == 200 and response.json():
            record = response.json()[0]
            if display:
                display_telemetry_record(record, f"📡 LATEST READING: {device_id}")
            return record
        return None
        
    except Exception as e:
        logger.error(f"Failed to get latest reading: {e}")
        return None

def get_history(device_id: str, limit: int = 100, display: bool = False):
    """Get historical data for a device from Supabase"""
    if not SUPABASE_AVAILABLE:
        return []
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}"
        }
        params = {
            "select": "*",
            "device_id": f"eq.{device_id}",
            "order": "timestamp.desc",
            "limit": limit
        }
        
        response = requests.get(url, headers=headers, params=params, timeout=5)
        
        if response.status_code == 200:
            records = response.json()
            if display and records:
                print(f"\n📜 HISTORY FOR {device_id} (Last {len(records)} records)")
                print("="*80)
                for i, record in enumerate(records[:10], 1):  # Show last 10
                    fuel_pct = record.get('fuel_percentage', 0)
                    location = record.get('location_name', 'Unknown')
                    timestamp = record.get('timestamp', 'N/A')
                    print(f"{i:2}. {format_timestamp(timestamp)[:16]} | Fuel: {fuel_pct:5.1f}% | 📍 {location[:30]}")
            return records
        return []
        
    except Exception as e:
        logger.error(f"Failed to get history: {e}")
        return []

def store_alert(device_id: str, alert_type: str, message: str, truck_id: str = None):
    """Store alert in Supabase"""
    if not SUPABASE_AVAILABLE:
        return False
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/alerts"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}",
            "Content-Type": "application/json",
            "Prefer": "return=minimal"
        }
        
        alert_data = {
            "device_id": device_id,
            "alert_type": alert_type,
            "message": message,
            "truck_id": truck_id,
            "is_resolved": False,
            "created_at": datetime.utcnow().isoformat()
        }
        
        # Remove None values
        alert_data = {k: v for k, v in alert_data.items() if v is not None}
        
        response = requests.post(url, json=alert_data, headers=headers, timeout=5)
        
        if response.status_code == 201:
            # Display alert nicely
            print(f"\n⚠️  ALERT TRIGGERED!")
            print(f"   Type: {alert_type}")
            print(f"   Message: {message}")
            if truck_id:
                print(f"   Truck: {truck_id}")
            logger.info(f"✅ Stored alert for {device_id}")
            return True
        else:
            print(f"❌ Alert storage failed: {response.status_code}")
            return False
        
    except Exception as e:
        logger.error(f"Failed to store alert: {e}")
        return False

def get_truck_summary(truck_id: str, display: bool = False):
    """Get fuel summary for a specific truck"""
    if not SUPABASE_AVAILABLE:
        return None
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}"
        }
        params = {
            "select": "*",
            "truck_id": f"eq.{truck_id}",
            "order": "timestamp.desc",
            "limit": 1
        }
        
        response = requests.get(url, headers=headers, params=params, timeout=5)
        
        if response.status_code == 200 and response.json():
            record = response.json()[0]
            if display:
                display_telemetry_record(record, f"🚛 TRUCK SUMMARY: {truck_id}")
            return record
        return None
        
    except Exception as e:
        logger.error(f"Failed to get truck summary: {e}")
        return None

def get_fleet_status(display: bool = True):
    """Get current status for all trucks"""
    if not SUPABASE_AVAILABLE:
        return []
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}"
        }
        
        params = {
            "select": "truck_id,license_plate,driver_name,fuel_percentage,fuel_volume_liters,status,timestamp,device_id,tank_capacity_liters,location_name,speed_kmh,odometer_km",
            "order": "timestamp.desc",
            "limit": 100
        }
        
        response = requests.get(url, headers=headers, params=params, timeout=5)
        
        if response.status_code == 200:
            # Get unique latest reading per truck
            trucks_data = {}
            for record in response.json():
                truck_id = record.get('truck_id')
                if truck_id and truck_id not in trucks_data:
                    trucks_data[truck_id] = record
            
            fleet_list = list(trucks_data.values())
            
            if display and fleet_list:
                display_fleet_status(fleet_list)
            
            return fleet_list
        return []
        
    except Exception as e:
        logger.error(f"Failed to get fleet status: {e}")
        return []

def get_active_alerts(display: bool = True):
    """Get active (unresolved) alerts"""
    if not SUPABASE_AVAILABLE:
        return []
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/alerts"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}"
        }
        params = {
            "select": "*",
            "is_resolved": "eq.false",
            "order": "created_at.desc",
            "limit": 20
        }
        
        response = requests.get(url, headers=headers, params=params, timeout=5)
        
        if response.status_code == 200:
            alerts = response.json()
            if display and alerts:
                display_alerts(alerts)
            return alerts
        return []
        
    except Exception as e:
        logger.error(f"Failed to get alerts: {e}")
        return []

def update_sensor_fuel(device_id: str, decrease_amount: float = None):
    """Update fuel percentage for a specific sensor"""
    if not SUPABASE_AVAILABLE:
        return None
    
    try:
        # Get current latest reading
        current = get_latest_reading(device_id)
        if not current:
            print(f"⚠️ No existing data for sensor {device_id}, creating new record")
            return None
        
        # Calculate new fuel percentage
        current_fuel = current.get('fuel_percentage', 100)
        
        # Random decrease if not specified (0.5% to 3% per reading)
        if decrease_amount is None:
            decrease_amount = random.uniform(0.5, 3.0)
        
        new_fuel = max(0, current_fuel - decrease_amount)
        
        # Create new telemetry record with location
        new_record = {
            "device_id": device_id,
            "sensor_type": current.get('sensor_type', 'ultrasonic'),
            "raw_value_cm": current.get('raw_value_cm', 0),
            "temperature_c": current.get('temperature_c', 25.0),
            "fuel_level_cm": current.get('fuel_level_cm', 0) * (new_fuel / current_fuel) if current_fuel > 0 else 0,
            "fuel_percentage": new_fuel,
            "fuel_volume_liters": current.get('tank_capacity_liters', 300) * (new_fuel / 100),
            "status": determine_status(new_fuel),
            "truck_id": current.get('truck_id'),
            "driver_name": current.get('driver_name'),
            "license_plate": current.get('license_plate'),
            "tank_capacity_liters": current.get('tank_capacity_liters', 300),
            "timestamp": datetime.utcnow().isoformat(),
            # Preserve location data
            "latitude": current.get('latitude'),
            "longitude": current.get('longitude'),
            "location_name": current.get('location_name'),
            "odometer_km": current.get('odometer_km', 0) + (decrease_amount * 0.5),  # Rough estimate
            "speed_kmh": current.get('speed_kmh', 50),
            "trip_distance_km": current.get('trip_distance_km', 0) + decrease_amount
        }
        
        # Store the new reading
        success = store_telemetry(new_record)
        
        if success:
            # Check if alert needed
            if new_fuel < 15:
                store_alert(
                    device_id=device_id,
                    alert_type="LOW" if new_fuel >= 10 else "CRITICAL",
                    message=f"Fuel level at {new_fuel:.1f}% - {'Critical' if new_fuel < 10 else 'Low'} fuel warning",
                    truck_id=current.get('truck_id')
                )
            
            print(f"   📉 Fuel decreased by {decrease_amount:.2f}% → {new_fuel:.1f}%")
        
        return new_record
        
    except Exception as e:
        logger.error(f"Failed to update fuel for {device_id}: {e}")
        return None

def determine_status(fuel_percentage: float) -> str:
    """Determine status based on fuel percentage"""
    if fuel_percentage >= 75:
        return "NORMAL"
    elif fuel_percentage >= 50:
        return "NORMAL"
    elif fuel_percentage >= 25:
        return "WARNING"
    elif fuel_percentage >= 10:
        return "LOW"
    else:
        return "CRITICAL"

def run_sensor_simulation(num_sensors: int = None, interval_seconds: int = 5, max_iterations: int = None):
    """
    Run simulation for specified number of sensors
    
    Args:
        num_sensors: Number of sensors to simulate (if None, uses all available)
        interval_seconds: Seconds between readings
        max_iterations: Maximum number of iterations (None for unlimited)
    """
    print("="*60)
    print("🚛 FUEL SENSOR SIMULATION")
    print("="*60)
    
    # Get available sensors
    available_sensors = get_available_sensors()
    
    if not available_sensors:
        print("\n❌ No sensors found in database!")
        print("   Please add some sensors first using initial setup.")
        return
    
    # Determine number of sensors to simulate
    if num_sensors is None or num_sensors > len(available_sensors):
        num_sensors = len(available_sensors)
        print(f"\n📡 Simulating ALL {num_sensors} available sensors")
    else:
        print(f"\n📡 Simulating {num_sensors} out of {len(available_sensors)} available sensors")
    
    # Select sensors to simulate
    sensors_to_simulate = random.sample(available_sensors, num_sensors)
    
    print(f"\n🎯 Selected sensors:")
    for i, sensor in enumerate(sensors_to_simulate, 1):
        print(f"   {i}. {sensor['device_id']} - {sensor['truck_id']} ({sensor['license_plate']}) - Current fuel: {sensor['current_fuel']:.1f}% - 📍 {sensor.get('location', 'Unknown')}")
    
    print(f"\n⏰ Simulation settings:")
    print(f"   Interval: {interval_seconds} seconds")
    print(f"   Max iterations: {max_iterations if max_iterations else 'Unlimited'}")
    print(f"   Press Ctrl+C to stop\n")
    
    iteration = 0
    
    try:
        while True:
            iteration += 1
            
            if max_iterations and iteration > max_iterations:
                print(f"\n✅ Reached maximum iterations ({max_iterations})")
                break
            
            print(f"\n{'='*60}")
            print(f"📊 ITERATION {iteration} - {datetime.now().strftime('%H:%M:%S')}")
            print(f"{'='*60}")
            
            # Update each sensor
            for sensor in sensors_to_simulate:
                device_id = sensor['device_id']
                print(f"\n🔄 Updating {device_id}...")
                
                # Random decrease amount (0.5% to 3%)
                decrease = random.uniform(0.5, 3.0)
                new_record = update_sensor_fuel(device_id, decrease)
                
                if new_record:
                    # Update the sensor's current fuel in our list
                    sensor['current_fuel'] = new_record.get('fuel_percentage', 0)
                    
                    # Display current status
                    fuel_pct = new_record.get('fuel_percentage', 0)
                    status = new_record.get('status', 'UNKNOWN')
                    status_emoji = get_status_emoji(fuel_pct)
                    
                    print(f"   {status_emoji} New fuel: {fuel_pct:.1f}% | Status: {status}")
            
            # Show fleet status after each iteration
            print(f"\n📊 Updating fleet dashboard...")
            get_fleet_status(display=True)
            
            # Show active alerts
            get_active_alerts(display=True)
            
            # Wait for next iteration
            if not (max_iterations and iteration >= max_iterations):
                print(f"\n⏳ Waiting {interval_seconds} seconds until next reading...")
                time.sleep(interval_seconds)
                
    except KeyboardInterrupt:
        print(f"\n\n⚠️ Simulation stopped by user after {iteration} iterations")
        print(f"📊 Final fleet status:")
        get_fleet_status(display=True)

def test_connection():
    """Test if Supabase connection works"""
    if not SUPABASE_AVAILABLE:
        print("❌ Supabase not configured")
        print("   Make sure SUPABASE_URL and SUPABASE_KEY are set in .env file")
        return False
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/"
        headers = {"apikey": SUPABASE_KEY}
        response = requests.get(url, headers=headers, timeout=5)
        
        if response.status_code == 200:
            print("✅ Supabase connection successful!")
            return True
        else:
            print(f"❌ Supabase connection failed: {response.status_code}")
            print(f"   Response: {response.text[:200]}")
            return False
    except Exception as e:
        print(f"❌ Supabase connection error: {e}")
        return False

# Test connection when module runs directly
if __name__ == "__main__":
    import sys
    
    print("="*50)
    print("Supabase REST Client with Fuel Simulation")
    print("="*50)
    
    # Test connection
    test_connection()
    
    if SUPABASE_AVAILABLE:
        # Parse command line arguments
        num_sensors = None
        interval = 5
        max_iter = None
        
        if len(sys.argv) > 1:
            try:
                num_sensors = int(sys.argv[1])
                print(f"\n📡 Will simulate {num_sensors} sensor(s)")
            except:
                print(f"\n⚠️ Invalid sensor count, using all available")
        
        if len(sys.argv) > 2:
            try:
                interval = int(sys.argv[2])
                print(f"⏰ Interval: {interval} seconds")
            except:
                pass
        
        if len(sys.argv) > 3:
            try:
                max_iter = int(sys.argv[3])
                print(f"🔄 Max iterations: {max_iter}")
            except:
                pass
        
        # Show menu
        print("\n📋 Available options:")
        print("   1. Show current fleet status")
        print("   2. Show active alerts")
        print("   3. Run sensor simulation (decreasing fuel)")
        print("   4. Run simulation with custom parameters")
        print("   5. Exit")
        
        choice = input("\n👉 Select option (1-5): ").strip()
        
        if choice == '1':
            get_fleet_status(display=True)
        elif choice == '2':
            get_active_alerts(display=True)
        elif choice == '3':
            run_sensor_simulation(
                num_sensors=num_sensors,
                interval_seconds=interval,
                max_iterations=max_iter
            )
        elif choice == '4':
            try:
                n = int(input("Number of sensors to simulate: ") or num_sensors or 3)
                i = int(input("Interval in seconds: ") or interval)
                m = input("Max iterations (Enter for unlimited): ")
                m = int(m) if m.strip() else None
                run_sensor_simulation(num_sensors=n, interval_seconds=i, max_iterations=m)
            except ValueError:
                print("❌ Invalid input, using defaults")
                run_sensor_simulation(num_sensors=num_sensors, interval_seconds=interval, max_iterations=max_iter)
        else:
            print("👋 Goodbye!")
    else:
        print("\n❌ Cannot run simulation without Supabase connection")
        print("   Please configure SUPABASE_URL and SUPABASE_KEY in .env file")