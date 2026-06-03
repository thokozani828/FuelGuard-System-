# quick_setup.py
import os
import requests

# Your Supabase URL (you provided this)
SUPABASE_URL = "https://shdaldiqnbtlgjajxroi.supabase.co"

# Get the anon key from user
print("\n" + "="*60)
print(" SUPABASE SETUP ")
print("="*60)
print(f"\nSupabase URL: {SUPABASE_URL}")
print("\nPlease go to your Supabase dashboard:")
print("1. Project Settings > API")
print("2. Copy the 'anon public' key")
print("3. Paste it below\n")

SUPABASE_KEY = input("eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkwMzA0MTcsImV4cCI6MjA5NDYwNjQxN30.BDRnisUkar6CaBKc0-AI6IXw16yfgjrkqEv59PWkJIo ").strip()

if not SUPABASE_KEY:
    print("\n❌ Anon Key is required!")
    exit(1)

# Create .env file
os.makedirs('gateway', exist_ok=True)

env_content = f"""SUPABASE_URL={SUPABASE_URL}
SUPABASE_KEY={SUPABASE_KEY}
"""

with open('gateway/.env', 'w') as f:
    f.write(env_content)

print("\n✅ .env file created in gateway/.env")

# Test connection
print("\n🔍 Testing Supabase connection...")

headers = {
    "apikey": SUPABASE_KEY,
    "Authorization": f"Bearer {SUPABASE_KEY}",
    "Content-Type": "application/json"
}

try:
    # Test fetching trucks
    response = requests.get(f"{SUPABASE_URL}/rest/v1/trucks", headers=headers, timeout=10)
    
    if response.status_code == 200:
        print("✅ Successfully connected to Supabase!")
        trucks = response.json()
        print(f"📊 Found {len(trucks)} trucks in database")
        
        if len(trucks) == 0:
            print("\n⚠️ No trucks found. Let's create some sample trucks.")
            
            # Create sample trucks
            sample_trucks = [
                {
                    "truck_id": "TRK001",
                    "license_plate": "ABC-1234",
                    "driver_name": "John Smith",
                    "driver_phone": "+27 71 234 5678",
                    "driver_email": "john@fleetguard.com",
                    "tank_capacity_liters": 300.0,
                    "tank_height_cm": 120.0,
                    "tank_cross_section_m2": 2.5,
                    "current_mileage_km": 45000.0,
                    "status": "ACTIVE",
                    "fuel_level": 75.0
                },
                {
                    "truck_id": "TRK002",
                    "license_plate": "XYZ-5678",
                    "driver_name": "Sarah Johnson",
                    "driver_phone": "+27 72 345 6789",
                    "driver_email": "sarah@fleetguard.com",
                    "tank_capacity_liters": 350.0,
                    "tank_height_cm": 130.0,
                    "tank_cross_section_m2": 2.7,
                    "current_mileage_km": 32000.0,
                    "status": "ACTIVE",
                    "fuel_level": 45.0
                },
                {
                    "truck_id": "TRK003",
                    "license_plate": "DEF-9012",
                    "driver_name": "Mike Wilson",
                    "driver_phone": "+27 73 456 7890",
                    "driver_email": "mike@fleetguard.com",
                    "tank_capacity_liters": 400.0,
                    "tank_height_cm": 140.0,
                    "tank_cross_section_m2": 2.9,
                    "current_mileage_km": 28000.0,
                    "status": "ACTIVE",
                    "fuel_level": 82.0
                }
            ]
            
            print("\n📝 Creating sample trucks...")
            for truck in sample_trucks:
                insert_response = requests.post(
                    f"{SUPABASE_URL}/rest/v1/trucks",
                    headers=headers,
                    json=truck
                )
                if insert_response.status_code in [200, 201, 204]:
                    print(f"  ✅ Created truck: {truck['truck_id']} - {truck['license_plate']}")
                else:
                    print(f"  ❌ Failed to create {truck['truck_id']}: {insert_response.status_code}")
            
            print("\n✅ Sample trucks created!")
            
    elif response.status_code == 401:
        print("❌ Authentication failed! Please check your Anon Key.")
        print("   Make sure you copied the correct 'anon public' key from Supabase.")
        exit(1)
    else:
        print(f"❌ Connection failed: {response.status_code}")
        print(f"   Response: {response.text}")
        exit(1)
        
except Exception as e:
    print(f"❌ Connection error: {e}")
    exit(1)

print("\n" + "="*60)
print("✅ SETUP COMPLETE!")
print("="*60)
print("\nYou can now run:")
print("  python webDashboard.py")
print("\nThis will:")
print("  - Connect to your Supabase database")
print("  - Load active trucks")
print("  - Generate real-time telemetry")
print("  - Store telemetry in the database")
print("  - Detect and store siphon alerts")
print("="*60)