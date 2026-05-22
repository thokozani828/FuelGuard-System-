# models.py
from pydantic import BaseModel, Field, field_validator
from datetime import datetime
from typing import Optional
from enum import Enum

class SensorType(str, Enum):
    ULTRASONIC = "ultrasonic"
    PRESSURE = "pressure"
    TEMPERATURE = "temperature"
    RADAR = "radar"  # Added for fleet sensors
    OPTICAL = "optical"  # Added for fleet sensors
    CAPACITIVE = "capacitive"  # Added for fleet sensors

class TelemetryData(BaseModel):
    device_id: str = Field(..., description="Unique device identifier")
    sensor_type: SensorType
    raw_value: float = Field(..., description="Raw sensor reading in cm")
    temperature: Optional[float] = Field(None, description="Ambient temperature in °C")
    timestamp: datetime = Field(default_factory=datetime.utcnow)
    
    # Optional fields for fleet management (your API can ignore these)
    truck_id: Optional[str] = Field(None, description="Assigned truck ID")
    driver_name: Optional[str] = Field(None, description="Driver name")
    license_plate: Optional[str] = Field(None, description="Truck license plate")
    
    @field_validator('raw_value')
    @classmethod
    def validate_distance(cls, v: float) -> float:
        if v < 0 or v > 300:
            raise ValueError(f"Invalid distance: {v}cm. Must be between 0-300cm")
        return v
    
    @field_validator('temperature')
    @classmethod
    def validate_temperature(cls, v: Optional[float]) -> Optional[float]:
        if v is not None and (v < -40 or v > 85):
            raise ValueError(f"Invalid temperature: {v}°C. Must be between -40°C and 85°C")
        return v

class FuelLevelResponse(BaseModel):
    device_id: str
    fuel_level_cm: float
    fuel_level_percentage: float
    fuel_volume_liters: float
    tank_capacity_liters: float
    status: str  # NORMAL, LOW, CRITICAL, EMPTY, FULL
    timestamp: datetime
    
    # Optional fleet information
    truck_id: Optional[str] = None
    license_plate: Optional[str] = None
    driver_name: Optional[str] = None

class TankConfig(BaseModel):
    tank_id: str
    tank_height_cm: float
    tank_cross_section_area_m2: float
    dead_zone_cm: float = 5.0
    max_capacity_liters: float
    
    # Optional fleet fields
    truck_id: Optional[str] = None
    license_plate: Optional[str] = None
    driver_name: Optional[str] = None
    
    @property
    def usable_height_cm(self) -> float:
        return self.tank_height_cm - self.dead_zone_cm
    
    @property
    def capacity_liters(self) -> float:
        """Calculate actual capacity based on tank dimensions"""
        volume_m3 = (self.tank_height_cm / 100) * self.tank_cross_section_area_m2
        return volume_m3 * 1000

# Fleet configuration helper
class FleetTruckConfig(BaseModel):
    """Configuration for trucks in fleet"""
    truck_id: str
    license_plate: str
    driver_name: str
    sensor_id: str
    tank_config: TankConfig
    
class FleetSensorAssignment(BaseModel):
    """Sensor to truck assignment"""
    sensor_id: str
    truck_id: str
    assigned_at: datetime = Field(default_factory=datetime.utcnow)
    is_active: bool = True