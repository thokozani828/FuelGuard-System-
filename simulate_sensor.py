import requests
import random
import time

API_URL = "http://localhost/api/logs.php"

while True:
    sensor_distance = round(random.uniform(10, 90), 2)

    payload = {
        "vehicle_id": 1,
        "sensor_distance_cm": sensor_distance
    }

    response = requests.post(API_URL, json=payload)

    print(response.json())

    time.sleep(5)