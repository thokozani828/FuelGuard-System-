import requests
import os
from datetime import datetime
from dotenv import load_dotenv

load_dotenv()

SUPABASE_URL = os.getenv("SUPABASE_URL", "").rstrip('/')
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")

print(f"Supabase URL: {SUPABASE_URL}")
print(f"Supabase Key: {SUPABASE_KEY[:50]}...")

headers = {
    "apikey": SUPABASE_KEY,
    "Authorization": f"Bearer {SUPABASE_KEY}",
    "Content-Type": "application/json"
}

# Test 1: Check if tables exist
print("\n1. Checking tables...")
tables = ['trucks', 'fuel_telemetry', 'alerts']
for table in tables:
    try:
        response = requests.get(f"{SUPABASE_URL}/rest/v1/{table}?limit=1", headers=headers)
        print(f"   {table}: {'✅ EXISTS' if response.status_code == 200 else f'❌ Error {response.status_code}'}")
    except Exception as e:
        print(f"   {table}: ❌ {e}")

# Test 2: Insert a simple test record
print("\n2. Testing insert into fuel_telemetry...")

test_data = {
    "device_id": "TEST001",
    "sensor_type": "Test",
    "raw_value_cm": 120.0,
    "temperature_c": 25.0,
    "fuel_level_cm": 120.0,
    "fuel_percentage": 100.0,
    "fuel_volume_liters": 400.0,
    "status": "TEST",
    "truck_id": "TRK001",
    "driver_name": "Test Driver",
    "driver_id": "DRV001",
    "license_plate": "TEST-123",
    "timestamp": datetime.now().isoformat()
}

print(f"   Sending: {test_data}")
response = requests.post(f"{SUPABASE_URL}/rest/v1/fuel_telemetry", headers=headers, json=test_data)
print(f"   Status: {response.status_code}")
print(f"   Response: {response.text}")

# Test 3: Check the actual table structure
print("\n3. Checking fuel_telemetry table structure...")
try:
    response = requests.get(f"{SUPABASE_URL}/rest/v1/fuel_telemetry", headers=headers)
    if response.status_code == 200:
        data = response.json()
        if data:
            print(f"   Columns in table: {list(data[0].keys())}")
        else:
            print("   Table exists but no data")
    else:
        print(f"   Error: {response.status_code}")
except Exception as e:
    print(f"   Error: {e}")