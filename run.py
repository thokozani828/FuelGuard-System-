import subprocess
import sys
import time
import requests

def check_api():
    """Check if API is running"""
    try:
        response = requests.get("http://localhost:8000/api/health")
        return response.status_code == 200
    except:
        return False

def main():
    print("=" * 60)
    print("🚀 FuelGuard IoT Gateway Launcher")
    print("=" * 60)
    
    # Start API server
    print("\n1️⃣ Starting API server...")
    api_process = subprocess.Popen(
        [sys.executable, "-m", "uvicorn", "gateway.api:app", "--host", "0.0.0.0", "--port", "8000", "--reload"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    
    # Wait for API to be ready
    print("   Waiting for API to start...")
    time.sleep(3)
    
    # Check if API is running
    if check_api():
        print("   ✅ API server is running on http://localhost:8000")
        print("   📖 API docs available at http://localhost:8000/docs")
    else:
        print("   ❌ Failed to start API server")
        return
    
    # Start simulator
    print("\n2️⃣ Starting sensor simulator...")
    simulator_process = subprocess.Popen(
        [sys.executable, "-m", "simulator.sensor_simulator", "--device-id", "tank_001", "--interval", "3"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    
    print("   ✅ Simulator is running")
    print("\n" + "=" * 60)
    print("🎉 System is running!")
    print("📊 Watch the telemetry data flowing...")
    print("🔍 Open http://localhost:8000/docs to test the API")
    print("⏹️  Press Ctrl+C to stop")
    print("=" * 60 + "\n")
    
    # Stream simulator output
    try:
        for line in simulator_process.stdout:
            print(line, end='')
    except KeyboardInterrupt:
        print("\n\n🛑 Stopping services...")
        api_process.terminate()
        simulator_process.terminate()
        print("✅ Services stopped")

if __name__ == "__main__":
    main()