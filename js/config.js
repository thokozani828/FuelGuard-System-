/**
 * FuelGuard Pro - Global Configuration
 * Update these URLs after deploying your backend to Render/Fly.io
 */
window.FuelGuardConfig = {
    // URL of your PHP Backend (Render Docker Service)
    API_BASE_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' 
        ? '' // Use relative path in local dev
        : 'https://fuelguard-backend.onrender.com', 

    // URL of your Python IoT Gateway (Render Web Service)
    GATEWAY_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
        ? 'http://localhost:8000'
        : 'https://fuelguard-system.onrender.com', 


    // Supabase Public Config
    SUPABASE_URL: 'https://shdaldiqnbtlgjajxroi.supabase.co',
    SUPABASE_KEY: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNoZGFsZGlxbmJ0bGdqYWp4cm9pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkwMzA0MTcsImV4cCI6MjA5NDYwNjQxN30.BDRnisUkar6CaBKc0-AI6IXw16yfgjrkqEv59PWkJIo' // Should be handled via environment variables in backend, but keep here if frontend needs it for direct Supabase calls
};
