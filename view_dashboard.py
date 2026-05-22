# view_dashboard.py
import os
import sys
import requests
from datetime import datetime
from dotenv import load_dotenv

# Add gateway folder to path and load .env from there
gateway_path = os.path.join(os.path.dirname(__file__), 'gateway')
if os.path.exists(gateway_path):
    load_dotenv(os.path.join(gateway_path, '.env'))
else:
    load_dotenv()  # Fallback to current directory

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_KEY")

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
        if timestamp_str:
            dt = datetime.fromisoformat(timestamp_str.replace('Z', '+00:00'))
            return dt.strftime("%Y-%m-%d %H:%M:%S")
    except:
        pass
    return timestamp_str[:19] if timestamp_str else "N/A"

def get_fleet_status():
    """Get current status for all trucks from Supabase"""
    if not SUPABASE_URL or not SUPABASE_KEY:
        print("❌ Supabase credentials not configured")
        print(f"   SUPABASE_URL: {SUPABASE_URL}")
        print(f"   SUPABASE_KEY: {SUPABASE_KEY[:20] if SUPABASE_KEY else 'None'}...")
        return []
    
    try:
        url = f"{SUPABASE_URL}/rest/v1/fuel_telemetry"
        headers = {
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}"
        }
        params = {
            "select": "truck_id,license_plate,driver_name,fuel_percentage,fuel_volume_liters,status,timestamp,device_id",
            "order": "timestamp.desc",
            "limit": 100
        }
        
        response = requests.get(url, headers=headers, params=params, timeout=10)
        
        if response.status_code == 200:
            # Get unique latest reading per truck
            trucks_data = {}
            for record in response.json():
                truck_id = record.get('truck_id')
                if truck_id and truck_id not in trucks_data:
                    trucks_data[truck_id] = record
            return list(trucks_data.values())
        else:
            print(f"❌ Failed to fetch data: {response.status_code}")
            if response.text:
                print(f"   Details: {response.text[:200]}")
            return []
            
    except Exception as e:
        print(f"❌ Error fetching fleet status: {e}")
        return []

def get_active_alerts():
    """Get active alerts from Supabase"""
    if not SUPABASE_URL or not SUPABASE_KEY:
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
        
        response = requests.get(url, headers=headers, params=params, timeout=10)
        
        if response.status_code == 200:
            return response.json()
        return []
        
    except Exception as e:
        print(f"❌ Error fetching alerts: {e}")
        return []

def display_fleet_status(records):
    """Display fleet status in a formatted table"""
    if not records:
        print("\n⚠️ No fleet data available")
        print("   Make sure sensors are sending data to the API")
        return
    
    print("\n" + "="*100)
    print(" 🚛 FLEET FUEL STATUS DASHBOARD ")
    print("="*100)
    print(f"📅 {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*100)
    
    # Header
    print(f"{'':<2} {'TRUCK':<8} {'PLATE':<12} {'DRIVER':<15} {'FUEL %':<8} {'VOLUME':<10} {'STATUS':<10} {'GAUGE'}")
    print("-"*100)
    
    # Display each truck
    for i, record in enumerate(records, 1):
        truck_id = record.get('truck_id', 'N/A')[:7]
        plate = record.get('license_plate', 'N/A')[:10]
        driver = record.get('driver_name', 'N/A')[:14]
        fuel_pct = record.get('fuel_percentage', 0)
        volume = record.get('fuel_volume_liters', 0)
        status = record.get('status', 'UNKNOWN')[:9]
        
        fuel_bar = create_fuel_bar(fuel_pct, 15)
        status_emoji = get_status_emoji(fuel_pct)
        
        print(f"{status_emoji} {truck_id:<8} {plate:<12} {driver:<15} {fuel_pct:>6.1f}%  {volume:>8.1f}L   {status:<10} {fuel_bar}")
    
    print("="*100)
    
    # Summary statistics
    total_trucks = len(records)
    if total_trucks > 0:
        avg_fuel = sum(r.get('fuel_percentage', 0) for r in records) / total_trucks
        critical_trucks = sum(1 for r in records if r.get('fuel_percentage', 0) < 15)
        low_trucks = sum(1 for r in records if 15 <= r.get('fuel_percentage', 0) < 30)
        good_trucks = sum(1 for r in records if r.get('fuel_percentage', 0) >= 50)
        
        print(f"\n📊 SUMMARY STATISTICS:")
        print(f"   Total Trucks     : {total_trucks}")
        print(f"   Average Fuel     : {avg_fuel:.1f}%")
        print(f"   🟢 Good (>50%)   : {good_trucks}")
        print(f"   🟠 Low (15-30%)  : {low_trucks}")
        print(f"   🔴 Critical (<15%): {critical_trucks}")
    print("="*100)

def display_alerts(alerts):
    """Display alerts in formatted way"""
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
        
        print(f"\n┌─────────────────────────────────────────────────────────────────────┐")
        print(f"│ {emoji} ALERT: {alert_type:<63} │")
        print(f"├─────────────────────────────────────────────────────────────────────┤")
        print(f"│  📝 Message : {message:<63} │")
        print(f"│  🚛 Truck   : {truck_id:<63} │")
        print(f"│  🕐 Time    : {format_timestamp(created_at):<63} │")
        print(f"└─────────────────────────────────────────────────────────────────────┘")

def get_latest_reading(device_id):
    """Get latest reading for a specific device"""
    if not SUPABASE_URL or not SUPABASE_KEY:
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
        
        response = requests.get(url, headers=headers, params=params, timeout=10)
        
        if response.status_code == 200 and response.json():
            return response.json()[0]
        return None
        
    except Exception as e:
        print(f"❌ Error fetching reading: {e}")
        return None

def main():
    print("\n" + "="*60)
    print(" FUELGUARD FLEET DASHBOARD ")
    print("="*60)
    
    # Check credentials
    if not SUPABASE_URL or not SUPABASE_KEY:
        print("\n❌ Supabase not configured!")
        print("   Please set SUPABASE_URL and SUPABASE_KEY in gateway/.env file")
        print(f"\n   Current values:")
        print(f"   SUPABASE_URL: {SUPABASE_URL}")
        print(f"   SUPABASE_KEY: {SUPABASE_KEY[:20] if SUPABASE_KEY else 'None'}...")
        return
    
    print(f"\n🔗 Connected to Supabase")
    print(f"   URL: {SUPABASE_URL[:40]}...")
    
    # Fetch and display fleet status
    print("\n📊 Fetching fleet data...")
    fleet_data = get_fleet_status()
    
    if fleet_data:
        display_fleet_status(fleet_data)
    else:
        print("\n⚠️ No fleet data found.")
        print("   Possible reasons:")
        print("   1. No sensors have sent data yet")
        print("   2. The fuel_telemetry table is empty")
        print("   3. Run the simulator first: python fleet_sensor_simulator.py --mode auto")
    
    # Fetch and display alerts
    print("\n🔔 Fetching alerts...")
    alerts = get_active_alerts()
    display_alerts(alerts)
    
    # Show individual sensor readings
    print("\n" + "="*60)
    print(" INDIVIDUAL SENSOR STATUS ")
    print("="*60)
    
    sensors = ["SENSOR_001", "SENSOR_002", "SENSOR_003", "SENSOR_004", "SENSOR_005"]
    
    for sensor in sensors:
        reading = get_latest_reading(sensor)
        if reading:
            fuel_pct = reading.get('fuel_percentage', 0)
            fuel_bar = create_fuel_bar(fuel_pct, 15)
            timestamp = reading.get('timestamp', 'N/A')
            print(f"\n📡 {sensor}:")
            print(f"   ⛽ Fuel: {fuel_pct:.1f}% {fuel_bar}")
            print(f"   🕐 Last update: {format_timestamp(timestamp)}")
        else:
            print(f"\n📡 {sensor}: No data yet")
    
    print("\n" + "="*60)
    print(" Dashboard ready! Run again to refresh.")
    print("="*60)

if __name__ == "__main__":
    main()