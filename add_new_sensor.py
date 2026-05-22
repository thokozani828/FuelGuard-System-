# add_new_sensor.py
import json
import os

def add_new_sensor():
    print("="*50)
    print("ADD NEW SENSOR TO FLEET")
    print("="*50)
    
    # Get sensor info
    sensor_id = input("Sensor ID (e.g., SENSOR_006): ").strip()
    truck_id = input("Truck ID (e.g., TRK006): ").strip()
    license_plate = input("License Plate: ").strip()
    driver_name = input("Driver Name: ").strip()
    tank_capacity = float(input("Tank Capacity (Liters): ") or "340")
    tank_height = float(input("Tank Height (cm): ") or "135")
    
    # Update fleet_config.json
    config_path = 'fleet_config.json'
    if os.path.exists(config_path):
        with open(config_path, 'r') as f:
            config = json.load(f)
    else:
        config = {"trucks": [], "sensors": [], "assignments": []}
    
    # Check if already exists
    if any(t['id'] == truck_id for t in config['trucks']):
        print(f"⚠️  Truck {truck_id} already exists")
    else:
        # Add new truck
        config['trucks'].append({
            "id": truck_id,
            "plate": license_plate,
            "driver": driver_name,
            "capacity": tank_capacity,
            "height": tank_height
        })
        print(f"✅ Added truck {truck_id}")
    
    # Check if sensor exists
    if any(s['id'] == sensor_id for s in config['sensors']):
        print(f"⚠️  Sensor {sensor_id} already exists")
    else:
        # Add new sensor
        config['sensors'].append({
            "id": sensor_id,
            "type": "ultrasonic"
        })
        print(f"✅ Added sensor {sensor_id}")
    
    # Check if assignment exists
    assignment_exists = any(a['sensor_id'] == sensor_id for a in config['assignments'])
    if not assignment_exists:
        config['assignments'].append({
            "sensor_id": sensor_id,
            "truck_id": truck_id
        })
        print(f"✅ Assigned {sensor_id} → {truck_id}")
    
    # Save config
    with open(config_path, 'w') as f:
        json.dump(config, f, indent=2)
    
    print(f"\n✅ Fleet configuration saved to {config_path}")
    
    # Update API tank configs
    print("\n⚠️  IMPORTANT: You also need to update gateway/api.py")
    print(f"\nAdd this to TANK_CONFIGS in gateway/api.py:")
    print(f"""
    "{sensor_id}": TankConfig(
        tank_id="{truck_id}",
        tank_height_cm={tank_height},
        tank_cross_section_area_m2={tank_capacity / 1000 / (tank_height/100):.2f},
        dead_zone_cm=5.0,
        max_capacity_liters={tank_capacity}.0
    ),
""")
    
    print("\nAfter adding, restart your API:")
    print("  python -m uvicorn gateway.api:app --reload --port 8000")

if __name__ == "__main__":
    add_new_sensor()