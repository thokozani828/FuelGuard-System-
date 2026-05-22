# init_db.py
from config.database import engine, Base
from models import Truck, Sensor, FuelLog

def init_database():
    print("Creating database tables...")
    Base.metadata.create_all(bind=engine)
    print("✅ Database tables created successfully!")

if __name__ == "__main__":
    init_database()