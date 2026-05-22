# test_original_format.py
import requests
from datetime import datetime

# This is what your original simulator sent
telemetry = {
    "device_id": "tank_001",
    "sensor_type": "ultrasonic",
    "raw_value": 50.5,
    "temperature": 25.0,
    "timestamp": datetime.now().isoformat()
}

response = requests.post("http://localhost:8000/api/logs", json=telemetry)
print(f"Status: {response.status_code}")
if response.status_code == 422:
    print("Error details:", response.json())
else:
    print("Success:", response.json())

