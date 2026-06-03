# setup_supabase.py
import os

def setup_supabase():
    """Interactive setup for Supabase credentials"""
    
    print("\n" + "="*60)
    print(" FUELGUARD SUPABASE SETUP ")
    print("="*60)
    
    # Get credentials from user
    print("\nPlease enter your Supabase credentials:")
    print("(Find these in your Supabase dashboard under Project Settings > API)")
    
    supabase_url = input("\https://shdaldiqnbtlgjajxroi.supabase.co: ").strip()
    supabase_key = input("eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkwMzA0MTcsImV4cCI6MjA5NDYwNjQxN30.BDRnisUkar6CaBKc0-AI6IXw16yfgjrkqEv59PWkJIo").strip()
    
    if not supabase_url or not supabase_key:
        print("\n❌ Both URL and Key are required!")
        return False
    
    # Create gateway directory
    os.makedirs('gateway', exist_ok=True)
    
    # Write .env file
    env_content = f"""SUPABASE_URL={supabase_url}
SUPABASE_KEY={supabase_key}
"""
    
    with open('gateway/.env', 'w') as f:
        f.write(env_content)
    
    print("\n✅ .env file created successfully!")
    print(f"   Location: gateway/.env")
    
    # Test connection
    print("\n🔍 Testing connection...")
    
    import requests
    
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}"
    }
    
    try:
        response = requests.get(f"{supabase_url}/rest/v1/trucks", headers=headers, timeout=10)
        
        if response.status_code == 200:
            print("✅ Successfully connected to Supabase!")
            trucks = response.json()
            print(f"   Found {len(trucks)} trucks in database")
            
            if len(trucks) == 0:
                print("\n⚠️ No trucks found. Please add trucks to your database.")
                print("   Run the SQL script provided in the setup instructions.")
        else:
            print(f"❌ Connection failed: {response.status_code}")
            print(f"   Response: {response.text}")
            return False
            
    except Exception as e:
        print(f"❌ Connection error: {e}")
        return False
    
    print("\n" + "="*60)
    print(" Setup complete! You can now run: python webDashboard.py")
    print("="*60)
    
    return True

if __name__ == "__main__":
    setup_supabase()