# add_trucks_to_supabase.py
import requests
import os
from dotenv import load_dotenv

load_dotenv()

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_KEY")

if not SUPABASE_URL or not SUPABASE_KEY:
    print("❌ Supabase credentials not found in .env")
    exit(1)

headers = {
    "apikey": SUPABASE_KEY,
    "Authorization": f"Bearer {SUPABASE_KEY}",
    "Content-Type": "application/json"
}

# Trucks data
trucks = [
    {"truck_id": "TRK001", "license_plate": "ABC-1234", "driver_name": "John Smith", "fuel_capacity": 300.0, "tank_height": 120.0},
    {"truck_id": "TRK002", "license_plate": "XYZ-5678", "driver_name": "Sarah Johnson", "fuel_capacity": 350.0, "tank_height": 130.0},
    {"truck_id": "TRK003", "license_plate": "DEF-9012", "driver_name": "Mike Wilson", "fuel_capacity": 280.0, "tank_height": 115.0},
    {"truck_id": "TRK004", "license_plate": "GHI-3456", "driver_name": "Emma Brown", "fuel_capacity": 400.0, "tank_height": 140.0},
    {"truck_id": "TRK005", "license_plate": "JKL-7890", "driver_name": "David Lee", "fuel_capacity": 320.0, "tank_height": 125.0},
    {"truck_id": "TRK006", "license_plate": "YTR-6775", "driver_name": "Thokozani", "fuel_capacity": 300.0, "tank_height": 120.0},
]

# Add sensors
sensors = [
    {"sensor_id": "SENSOR_001", "sensor_type": "ultrasonic", "truck_id": "TRK001", "is_active": True},
    {"sensor_id": "SENSOR_002", "sensor_type": "ultrasonic", "truck_id": "TRK002", "is_active": True},
    {"sensor_id": "SENSOR_003", "sensor_type": "ultrasonic", "truck_id": "TRK003", "is_active": True},
    {"sensor_id": "SENSOR_004", "sensor_type": "ultrasonic", "truck_id": "TRK004", "is_active": True},
    {"sensor_id": "SENSOR_005", "sensor_type": "ultrasonic", "truck_id": "TRK005", "is_active": True},
    {"sensor_id": "SENSOR_006", "sensor_type": "ultrasonic", "truck_id": "TRK006", "is_active": True},
    {"sensor_id": "SENSOR_007", "sensor_type": "ultrasonic", "truck_id": None, "is_active": True},
]

print("="*50)
print("Adding trucks to Supabase...")
print("="*50)

# Add trucks
for truck in trucks:
    url = f"{SUPABASE_URL}/rest/v1/trucks"
    
    # Check if truck exists first
    check_url = f"{SUPABASE_URL}/rest/v1/trucks?truck_id=eq.{truck['truck_id']}"
    response = requests.get(check_url, headers=headers)
    
    if response.status_code == 200 and response.json():
        print(f"⏭️  Truck {truck['truck_id']} already exists")
    else:
        # Insert new truck
        response = requests.post(url, json=truck, headers=headers)
        if response.status_code == 201:
            print(f"✅ Added truck: {truck['truck_id']} - {truck['license_plate']}")
        else:
            print(f"❌ Failed to add truck {truck['truck_id']}: {response.status_code}")
            print(f"   {response.text}")

print("\n" + "="*50)
print("Adding sensors to Supabase...")
print("="*50)

# Add sensors
for sensor in sensors:
    url = f"{SUPABASE_URL}/rest/v1/sensors"
    
    # Check if sensor exists
    check_url = f"{SUPABASE_URL}/rest/v1/sensors?sensor_id=eq.{sensor['sensor_id']}"
    response = requests.get(check_url, headers=headers)
    
    if response.status_code == 200 and response.json():
        print(f"⏭️  Sensor {sensor['sensor_id']} already exists")
    else:
        # Insert new sensor
        response = requests.post(url, json=sensor, headers=headers)
        if response.status_code == 201:
            print(f"✅ Added sensor: {sensor['sensor_id']} -> Truck: {sensor['truck_id'] or 'Unassigned'}")
        else:
            print(f"❌ Failed to add sensor {sensor['sensor_id']}: {response.status_code}")
            print(f"   {response.text}")

print("\n" + "="*50)
print("✅ Setup complete! Now run the simulator again.")
print("="*50)