/**
 * FuelGuard Pro - Global Configuration
 * Update these URLs after deploying your backend to Render/Fly.io
 */
window.FuelGuardConfig = {
    // URL of your PHP Backend (Render Docker Service)
    API_BASE_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
        ? '' // Use relative path in local dev
        : 'https://fuelguard-backend.onrender.com', // 👈 REPLACE THIS after deploying backend

    // URL of your Python IoT Gateway (Render Web Service)
    GATEWAY_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
        ? 'http://localhost:8000'
        : 'https://fuelguard-system.onrender.com', // 👈 REPLACE THIS after deploying gateway

    // Supabase Public Config
    SUPABASE_URL: 'https://shdaldiqnbtlgjajxroi.supabase.co',
    SUPABASE_KEY: '' // Should be handled via environment variables in backend, but keep here if frontend needs it for direct Supabase calls
};
