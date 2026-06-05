# Use Nginx to serve the static drivers portal
FROM nginx:alpine

# Create the directory structure
RUN mkdir -p /usr/share/nginx/html/js

# Copy the drivers portal files to the root of Nginx
COPY drivers/ /usr/share/nginx/html/

# Copy the JS folder (needed for config.js)
COPY js/ /usr/share/nginx/html/js/

# Custom Nginx configuration to handle SPA-like behavior if needed, 
# or just ensure index.html is served for root
RUN echo 'server { \
    listen 80; \
    location / { \
        root /usr/share/nginx/html; \
        index index.html; \
        try_files $uri $uri/ /index.html; \
    } \
}' > /etc/nginx/conf.d/default.conf

# Expose port 80
EXPOSE 80

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
