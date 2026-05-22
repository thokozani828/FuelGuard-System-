# supabase_rest.py
import os
import requests
from datetime import datetime
from dotenv import load_dotenv
import logging

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
    
    print("\n" + "="*100)
    print(" 🚛 FLEET FUEL STATUS DASHBOARD ")
    print("="*100)
    print(f"📅 {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*100)
    
    # Header
    print(f"{'TRUCK':<8} {'PLATE':<12} {'DRIVER':<15} {'FUEL %':<8} {'VOLUME':<10} {'STATUS':<10} {'GAUGE'}")
    print("-"*100)
    
    # Display each truck
    for record in records:
        truck_id = record.get('truck_id', 'N/A')[:7]
        plate = record.get('license_plate', 'N/A')[:10]
        driver = record.get('driver_name', 'N/A')[:14]
        fuel_pct = record.get('fuel_percentage', 0)
        volume = record.get('fuel_volume_liters', 0)
        status = record.get('status', 'UNKNOWN')[:9]
        
        fuel_bar = create_fuel_bar(fuel_pct, 15)
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
        
        print(f"{color} {truck_id:<7} {plate:<12} {driver:<15} {fuel_pct:>6.1f}%  {volume:>8.1f}L   {status_emoji} {status:<9} {fuel_bar}")
    
    print("="*100)
    
    # Summary statistics
    total_trucks = len(records)
    avg_fuel = sum(r.get('fuel_percentage', 0) for r in records) / total_trucks if total_trucks > 0 else 0
    critical_trucks = sum(1 for r in records if r.get('fuel_percentage', 0) < 15)
    low_trucks = sum(1 for r in records if 15 <= r.get('fuel_percentage', 0) < 30)
    
    print(f"\n📊 SUMMARY:")
    print(f"   Total Trucks: {total_trucks}")
    print(f"   Average Fuel: {avg_fuel:.1f}%")
    print(f"   ⚠️  Critical (<15%): {critical_trucks}")
    print(f"   ⚠️  Low (15-30%): {low_trucks}")
    print("="*100)

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

def store_telemetry(data: dict):
    """Store telemetry data in Supabase using REST API"""
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
        
        # Prepare data for insertion
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
            "timestamp": data.get("timestamp", datetime.utcnow().isoformat())
        }
        
        # Remove None values
        telemetry_record = {k: v for k, v in telemetry_record.items() if v is not None}
        
        # Display what we're saving
        fuel_pct = telemetry_record.get('fuel_percentage', 0)
        device = telemetry_record.get('device_id', 'Unknown')
        truck = telemetry_record.get('truck_id', 'Unknown')
        
        print(f"\n💾 Saving to Supabase:")
        print(f"   📡 Sensor: {device}")
        print(f"   🚛 Truck: {truck}")
        print(f"   ⛽ Fuel: {fuel_pct:.1f}% {create_fuel_bar(fuel_pct)}")
        
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
                    timestamp = record.get('timestamp', 'N/A')
                    print(f"{i:2}. {format_timestamp(timestamp)[:16]} | Fuel: {fuel_pct:5.1f}% | Status: {record.get('status', 'N/A')}")
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
            "select": "truck_id,license_plate,driver_name,fuel_percentage,fuel_volume_liters,status,timestamp,device_id,tank_capacity_liters",
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
    print("="*50)
    print("Supabase REST Client Test")
    print("="*50)
    
    # Test connection
    test_connection()
    
    if SUPABASE_AVAILABLE:
        print("\n📊 Fetching fleet status...")
        get_fleet_status(display=True)
        
        print("\n⚠️ Fetching active alerts...")
        get_active_alerts(display=True)
        
        print("\n📝 Test storing a sample record...")
        sample_data = {
            "device_id": "TEST_001",
            "sensor_type": "ultrasonic",
            "raw_value_cm": 50.5,
            "temperature_c": 25.0,
            "fuel_level_cm": 69.5,
            "fuel_percentage": 57.9,
            "fuel_volume_liters": 173.7,
            "status": "NORMAL",
            "truck_id": "TRK001",
            "driver_name": "Test Driver",
            "license_plate": "TEST-123",
            "tank_capacity_liters": 300,
            "timestamp": datetime.utcnow().isoformat()
        }
        
        result = store_telemetry(sample_data)
        if result:
            print("✅ Test record stored successfully!")
        else:
            print("⚠️ Test record failed - check table columns")