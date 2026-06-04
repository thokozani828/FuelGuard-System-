# Use Nginx to serve the static frontend
FROM nginx:alpine

# Create the directory structure first
RUN mkdir -p /usr/share/nginx/html/js && \
    mkdir -p "/usr/share/nginx/html/drivers page"

# Copy the main HTML files to the root
COPY *.html /usr/share/nginx/html/

# Copy the JS folder and its contents
COPY js/ /usr/share/nginx/html/js/

# Copy the Drivers Page folder and its contents
COPY ["drivers page/", "/usr/share/nginx/html/drivers page/"]

# Expose port 80
EXPOSE 80

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
