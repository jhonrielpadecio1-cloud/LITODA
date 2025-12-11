FROM php:8.2-apache

# Install system dependencies for Python, OpenCV, and MySQL
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    libgl1 \
    libglib2.0-0 \
    default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Create Python virtual environment
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Install Python dependencies
COPY requirements.txt /tmp/requirements.txt
RUN pip install --upgrade pip setuptools wheel && \
    pip install --no-cache-dir -r /tmp/requirements.txt

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN a2enmod rewrite proxy proxy_http

# Copy custom Apache config
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copy application code
COPY . /var/www/html/

# Fix permissions for uploads directory (ensure it is writable)
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/uploads

# Copy startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Expose port (Railway assigns PORT env var, Apache listens on 80 by default)
# We will configure Apache to listen on the PORT env var in the start script
EXPOSE 80

CMD ["/start.sh"]
