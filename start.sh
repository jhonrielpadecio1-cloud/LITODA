#!/bin/bash
# Use PORT environment variable provided by Railway, default to 80
PORT=${PORT:-80}
# Configure Apache to listen on the correct port
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/000-default.conf
# Start Python backend in background
# We bind to 0.0.0.0:5000 to ensure it listens on all interfaces (internal localhost communication still works)
# We remove --daemon to keep logs visible in Railway console (using & to run in background)
echo "Starting Python Face Recognition System..."
gunicorn face_recognition_system:app --bind 0.0.0.0:5000 --timeout 120 --log-level debug --access-logfile - --error-logfile - &

# Start Apache in foreground
echo "Starting Apache..."
apache2-foreground
