FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    libjpeg-dev libx11-dev libxcomposite-dev libxcursor-dev libxdamage-dev libxi-dev \
    libxtst-dev libnss3-dev libatk-bridge2.0-0 libgtk-3-0 libdrm-dev libxrandr-dev \
    libgbm-dev libasound2 libxshmfence-dev libxss1 \
    ca-certificates fonts-liberation libappindicator3-1 \
    chromium \
    chromium-driver \
    --no-install-recommends

# ✅ Install PDO MySQL driver (needed by Laravel)
RUN docker-php-ext-install pdo pdo_mysql

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js (LTS) and npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get update && apt-get install -y nodejs

# Install Puppeteer globally
RUN npm install -g puppeteer

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Install PHP dependencies
RUN composer install

# Set correct permissions
RUN chown -R www-data:www-data /var/www
