# Deployment Plan: FuelGuard System

## 1. Recommended Deployment Target: Docker + VPS
Based on the project's hybrid tech stack (Python FastAPI Gateway, PHP Backend, and static Frontend) and the existing `docker-compose.yml`, I recommend deploying to a **Virtual Private Server (VPS)** using **Docker Compose**.

**Justification**:
- **Control & Complexity**: The system requires multiple runtimes (Python 3.10+ for IoT, PHP 8.x for API, Nginx for Frontend). Managing this on a PaaS like Render would require multiple services and potentially higher costs.
- **IoT Ready**: A VPS allows for easier setup of specific network rules or future MQTT brokers if needed for actual hardware integration.
- **Consistency**: Leveraging Docker ensures the development environment exactly matches production, reducing "it works on my machine" issues with PHP/Python versions.

## 2. Environment Variables
The following variables must be configured. Secrets (marked YES) must be added to GitHub Secrets for the CI/CD pipeline.

| Variable | Description | Example Value | Secret? |
|----------|-------------|---------------|---------|
| `SUPABASE_URL` | Supabase API endpoint | `https://xyz.supabase.co` | NO |
| `SUPABASE_KEY` | Supabase Service Role/Anon Key | `eyJhbGci...` | **YES** |
| `GATEWAY_API_KEY` | Key for IoT sensor ingestion | `fuelguard_secret_key` | **YES** |
| `DB_HOST` | Supabase Postgres Host | `db.xyz.supabase.co` | NO |
| `DB_PORT` | Supabase Postgres Port | `5432` | NO |
| `DB_NAME` | Database name | `postgres` | NO |
| `DB_USER` | Postgres Username | `postgres` | **YES** |
| `DB_PASS` | Postgres Password | `password123` | **YES** |
| `SMTP_USER` | Gmail/SMTP User | `user@gmail.com` | **YES** |
| `SMTP_PASS` | App-specific password | `abcd efgh ijkl` | **YES** |

## 3. Infrastructure Setup
1. **Provision VPS**: Provision a Linux VPS (Ubuntu 22.04 LTS recommended) with at least 2GB RAM.
2. **Install Docker**: Run the official Docker installation script.
3. **Database**: Use existing Supabase instance. Ensure the SQL schema is applied (use `init_db.py` or Supabase SQL editor).
4. **Networking**: 
   - Open Port 80/443 for Frontend/PHP API.
   - Open Port 8000 for IoT Gateway.
5. **SSL**: Use Nginx with Certbot (Let's Encrypt) to terminate SSL for all endpoints.

## 4. Docker Setup
### Gateway (Python FastAPI)
`infrastructure/docker/gateway/Dockerfile`:
```dockerfile
FROM python:3.11-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY gateway/ ./gateway/
COPY models.py .
EXPOSE 8000
CMD ["uvicorn", "gateway.api:app", "--host", "0.0.0.0", "--port", "8000"]
```

### Backend (PHP API)
`infrastructure/docker/backend/Dockerfile`:
```dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_pgsql
COPY api/ /var/www/html/api/
COPY config.php /var/www/html/
COPY db.php /var/www/html/
COPY login.php /var/www/html/
COPY register.php /var/www/html/
COPY .env /var/www/html/
RUN chown -R www-data:www-data /var/www/html
```

## 5. Database Migration Strategy
- **Initial Setup**: Run `python init_db.py` locally or via a one-off Docker container to create tables in Supabase.
- **Subsequent Deploys**: Use a dedicated migrations folder (not yet present) or apply SQL diffs via the Supabase Dashboard SQL Editor. ⚠️ **Ambiguous**: No formal migration framework (like Alembic) detected.

## 6. Rollback Strategy
- **Container Rollback**: The CI/CD pipeline tags images with the Git Commit SHA. To roll back, update the `docker-compose.yml` image tags to the previous SHA and run `docker compose up -d`.

## 7. Monitoring and Alerting
- **Uptime**: Use UptimeRobot to ping the Gateway `/api/health` and Backend `/api/test.php` every 5 minutes.
- **Alerting**: Detection failures are logged to Supabase. Recommend a scheduled GitHub Action (see `sensor-health-check.yml`) to verify data freshness.

---

## 8. Secrets Setup Guide (GitHub Actions)
Configure these in **Settings > Secrets and Variables > Actions**:

| Secret Name | Description |
|-------------|-------------|
| `DEPLOY_SSH_KEY` | Private key to access the VPS |
| `DEPLOY_HOST` | IP address or domain of the VPS |
| `DEPLOY_USER` | SSH username (e.g., `root` or `ubuntu`) |
| `SUPABASE_KEY` | Found in Supabase Settings > API |
| `DB_PASS` | Database password |
| `GATEWAY_API_KEY` | Defined in your `.env` |

**Bulk setup command**:
```bash
gh secret set SUPABASE_KEY --body "YOUR_KEY"
gh secret set DB_PASS --body "YOUR_PASS"
# ... repeat for others
```
