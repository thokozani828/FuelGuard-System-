# Use Nginx to serve the static frontend
FROM nginx:alpine

# Copy the HTML files to the Nginx html directory
COPY *.html /usr/share/nginx/html/

# Copy the JS folder
COPY js/ /usr/share/nginx/html/js/

# Copy the Drivers Page folder
COPY "drivers page/" "/usr/share/nginx/html/drivers page/"

# Expose port 80
EXPOSE 80

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
