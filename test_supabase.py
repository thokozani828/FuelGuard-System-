from gateway.supabase_rest import test_connection, store_telemetry

# Test connection
print("Testing Supabase connection...")
test_connection()

# Test storing data
test_data = {
    "device_id": "test_device",
    "sensor_type": "test",
    "raw_value_cm": 50.0,
    "temperature_c": 25.0,
    "fuel_level_cm": 70.0,
    "fuel_percentage": 58.33,
    "fuel_volume_liters": 1750.0,
    "status": "NORMAL"
}

print("\nTesting data storage...")
result = store_telemetry(test_data)
print(f"Storage result: {'Success' if result else 'Failed'}")