FROM python:3.11-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY gateway/ ./gateway/
COPY models.py .

# Ensure env vars can be injected
ENV PYTHONPATH=/app

EXPOSE 8000

# Start command
CMD ["uvicorn", "gateway.api:app", "--host", "0.0.0.0", "--port", "8000"]
