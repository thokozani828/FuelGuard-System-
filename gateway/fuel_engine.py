# fuel_engine.py
import numpy as np
from datetime import datetime
from typing import Dict, Optional, List
from .models import TelemetryData, FuelLevelResponse, TankConfig

class FuelLevelEngine:
    def __init__(self, tank_configs: Dict[str, TankConfig]):
        self.tank_configs = tank_configs
        self.calibration_factors: Dict[str, float] = {}
        
    def calculate_fuel_level(self, telemetry: TelemetryData) -> Optional[FuelLevelResponse]:
        # Check if device exists in configs (support both sensor_id and device_id)
        device_id = telemetry.device_id
        
        if device_id not in self.tank_configs:
            print(f"⚠️  Unknown device: {device_id}")
            print(f"   Available devices: {list(self.tank_configs.keys())}")
            return None
            
        config = self.tank_configs[device_id]
        
        # Apply temperature compensation
        compensated_distance = self._temperature_compensation(
            telemetry.raw_value, 
            telemetry.temperature
        )
        
        # Validate distance is within range
        if compensated_distance < 0 or compensated_distance > config.tank_height_cm + 10:
            print(f"⚠️  Invalid distance: {compensated_distance:.2f}cm for {device_id}")
            compensated_distance = max(0, min(compensated_distance, config.tank_height_cm))
        
        # Calculate fuel height (distance from bottom of tank to fuel surface)
        fuel_height_cm = config.tank_height_cm - compensated_distance
        
        # Apply dead zone (sensor can't measure below this point)
        fuel_height_cm = max(0, fuel_height_cm - config.dead_zone_cm)
        
        # Calculate percentage (use usable height)
        if config.usable_height_cm > 0:
            fuel_percentage = min(100, max(0, (fuel_height_cm / config.usable_height_cm) * 100))
        else:
            fuel_percentage = 0
        
        # Calculate volume in liters
        fuel_height_m = fuel_height_cm / 100
        volume_liters = fuel_height_m * config.tank_cross_section_area_m2 * 1000
        
        # Apply calibration if available
        if device_id in self.calibration_factors:
            volume_liters *= self.calibration_factors[device_id]
        
        # Ensure volume doesn't exceed max capacity
        volume_liters = min(volume_liters, config.max_capacity_liters)
        
        # Determine status
        status = self._determine_status(fuel_percentage)
        
        # Get fleet info from telemetry if available
        truck_id = getattr(telemetry, 'truck_id', None)
        driver_name = getattr(telemetry, 'driver_name', None)
        license_plate = getattr(telemetry, 'license_plate', None)
        
        return FuelLevelResponse(
            device_id=device_id,
            fuel_level_cm=round(fuel_height_cm, 2),
            fuel_level_percentage=round(fuel_percentage, 2),
            fuel_volume_liters=round(volume_liters, 2),
            tank_capacity_liters=config.max_capacity_liters,
            status=status,
            timestamp=datetime.utcnow(),
            truck_id=truck_id,
            driver_name=driver_name,
            license_plate=license_plate
        )
    
    def _temperature_compensation(self, distance_cm: float, temperature_c: Optional[float]) -> float:
        """
        Compensate distance reading based on temperature.
        Speed of sound changes with temperature.
        """
        if temperature_c is None:
            return distance_cm
        
        # Speed of sound formula: v = 331.3 + (0.606 * T) m/s
        # At 20°C: 343.4 m/s
        speed_at_20c = 331.3 + (0.606 * 20)  # ~343.4 m/s
        speed_at_temp = 331.3 + (0.606 * temperature_c)
        
        # Avoid division by zero
        if speed_at_temp <= 0:
            return distance_cm
        
        compensation_factor = speed_at_20c / speed_at_temp
        compensated_distance = distance_cm * compensation_factor
        
        return compensated_distance
    
    def _determine_status(self, percentage: float) -> str:
        """Determine fuel status based on percentage"""
        if percentage >= 90:
            return "FULL"
        elif percentage >= 75:
            return "HIGH"
        elif percentage >= 50:
            return "NORMAL"
        elif percentage >= 25:
            return "LOW"
        elif percentage >= 10:
            return "CRITICAL"
        elif percentage > 0:
            return "EMPTY"
        else:
            return "EMPTY"
    
    def calibrate_sensor(self, device_id: str, known_volume_liters: float, current_reading: float):
        """Calibrate sensor with known volume"""
        if device_id not in self.tank_configs:
            print(f"❌ Unknown device: {device_id}")
            return
        
        # Create telemetry object for calculation
        telemetry = TelemetryData(
            device_id=device_id, 
            sensor_type="ultrasonic", 
            raw_value=current_reading,
            timestamp=datetime.utcnow()
        )
        
        result = self.calculate_fuel_level(telemetry)
        
        if result and result.fuel_volume_liters > 0:
            calibration_factor = known_volume_liters / result.fuel_volume_liters
            self.calibration_factors[device_id] = calibration_factor
            print(f"✅ Calibrated {device_id}: factor = {calibration_factor:.3f}")
        else:
            print(f"❌ Calibration failed for {device_id}")
    
    def smooth_readings(self, readings: List[float], window_size: int = 5) -> float:
        """
        Apply moving average smoothing to readings
        """
        if not readings:
            return 0.0
            
        if len(readings) < window_size:
            return float(np.mean(readings))
        
        # Use last N readings for smoothing
        recent_readings = readings[-window_size:]
        
        # Remove outliers (optional)
        if len(recent_readings) >= 3:
            q1, q3 = np.percentile(recent_readings, [25, 75])
            iqr = q3 - q1
            lower_bound = q1 - 1.5 * iqr
            upper_bound = q3 + 1.5 * iqr
            filtered = [r for r in recent_readings if lower_bound <= r <= upper_bound]
            if filtered:
                return float(np.mean(filtered))
        
        return float(np.mean(recent_readings))
    
    def get_tank_info(self, device_id: str) -> Optional[Dict]:
        """Get tank configuration information"""
        if device_id in self.tank_configs:
            config = self.tank_configs[device_id]
            return {
                "device_id": device_id,
                "tank_id": config.tank_id,
                "tank_height_cm": config.tank_height_cm,
                "usable_height_cm": config.usable_height_cm,
                "cross_section_area_m2": config.tank_cross_section_area_m2,
                "max_capacity_liters": config.max_capacity_liters,
                "dead_zone_cm": config.dead_zone_cm,
                "truck_id": getattr(config, 'truck_id', None),
                "license_plate": getattr(config, 'license_plate', None),
                "driver_name": getattr(config, 'driver_name', None)
            }
        return None
    
    def add_tank_config(self, device_id: str, config: TankConfig):
        """Add or update tank configuration"""
        self.tank_configs[device_id] = config
        print(f"✅ Added tank config for {device_id}")
    
    def remove_tank_config(self, device_id: str):
        """Remove tank configuration"""
        if device_id in self.tank_configs:
            del self.tank_configs[device_id]
            if device_id in self.calibration_factors:
                del self.calibration_factors[device_id]
            print(f"✅ Removed tank config for {device_id}")