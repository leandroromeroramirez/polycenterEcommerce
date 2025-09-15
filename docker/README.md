# Docker Setup for Bagisto

This directory contains Docker configuration files for running Bagisto in containerized environments.

## Files Overview

- `Dockerfile` - Multi-stage production-ready Docker image
- `docker/nginx.conf` - Nginx web server configuration
- `docker/supervisord.conf` - Supervisor process manager configuration
- `docker/entrypoint.sh` - Application initialization script
- `docker/mysql/init.sql` - MySQL initialization script
- `docker-compose.yml` - Development environment (Laravel Sail)
- `docker-compose.prod.yml` - Production Docker Compose setup
- `.dockerignore` - Files to exclude from Docker build context
- `.env.docker` - Docker environment template
- `Makefile` - Commands for easy Docker management

## Quick Start

### Using Makefile (Recommended)

1. **Development Environment:**
```bash
# Start development environment
make dev

# Install Bagisto
make install

# View logs
make logs

# Access shell
make shell
```

2. **Production Environment:**
```bash
# Start production environment
make prod

# Install Bagisto
make install

# Optimize for production
make optimize
```

### Manual Setup

#### Development (using existing docker-compose.yml)
```bash
# Copy environment file
cp .env.docker .env

# Start development environment with Laravel Sail
./vendor/bin/sail up -d

# Or using docker-compose directly
docker-compose up -d

# Initialize application
make install
```

#### Production

1. **Setup environment:**
```bash
cp .env.docker .env
# Edit .env file with your production settings
```

2. **Build and run:**
```bash
docker-compose -f docker-compose.prod.yml up -d
```

3. **Initialize application:**
```bash
make ENV=prod install
```

## Configuration

### Environment Variables

Create a `.env` file or set environment variables:

```bash
# Application
APP_NAME=Bagisto
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bagisto
DB_USERNAME=bagisto
DB_PASSWORD=your_password

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Elasticsearch
SCOUT_DRIVER=elasticsearch
ELASTICSEARCH_HOST=elasticsearch:9200
```

### Volume Mounts

The production setup uses named volumes for:
- `mysql-data` - Database files
- `redis-data` - Redis persistence
- `elasticsearch-data` - Elasticsearch indices
- `app-storage` - Application file storage
- `app-logs` - Application logs

## Services

### Application (app)
- **Port:** 80
- **Components:** Nginx + PHP-FPM + Queue Workers + Scheduler
- **Health Check:** `/health` endpoint

### MySQL (mysql)
- **Port:** 3306 (internal)
- **Version:** 8.0
- **Database:** bagisto

### Redis (redis)
- **Port:** 6379 (internal)
- **Purpose:** Cache, sessions, queues

### Elasticsearch (elasticsearch)
- **Port:** 9200 (internal)
- **Purpose:** Product search and indexing

## Monitoring

### Health Checks
```bash
# Check all services
docker-compose -f docker-compose.prod.yml ps

# Check application health
curl http://localhost/health

# Check logs
docker-compose -f docker-compose.prod.yml logs -f app
```

### Resource Usage
```bash
# Monitor resource usage
docker stats

# Check specific container
docker exec bagisto-app top
```

## Scaling

### Horizontal Scaling
```bash
# Scale application containers
docker-compose -f docker-compose.prod.yml up -d --scale app=3

# Use a load balancer (nginx, traefik, etc.) in front
```

### Queue Workers
```bash
# Scale queue workers (modify supervisord.conf)
# Change numprocs value for queue-worker program

# Or run additional queue workers
docker exec bagisto-app php artisan queue:work --daemon
```

## Backup

### Database
```bash
# Backup
docker exec bagisto-mysql mysqldump -u bagisto -p bagisto > backup.sql

# Restore
docker exec -i bagisto-mysql mysql -u bagisto -p bagisto < backup.sql
```

### File Storage
```bash
# Backup storage volume
docker run --rm -v bagisto_app-storage:/data -v $(pwd):/backup alpine tar czf /backup/storage-backup.tar.gz -C /data .

# Restore storage volume
docker run --rm -v bagisto_app-storage:/data -v $(pwd):/backup alpine tar xzf /backup/storage-backup.tar.gz -C /data
```

## Troubleshooting

### Common Issues

1. **Permission Issues:**
```bash
# Fix storage permissions
docker exec bagisto-app chown -R www:www /var/www/html/storage
docker exec bagisto-app chmod -R 755 /var/www/html/storage
```

2. **Cache Issues:**
```bash
# Clear all caches
docker exec bagisto-app php artisan cache:clear
docker exec bagisto-app php artisan config:clear
docker exec bagisto-app php artisan route:clear
docker exec bagisto-app php artisan view:clear
```

3. **Queue Issues:**
```bash
# Restart queue workers
docker exec bagisto-app supervisorctl restart queue-worker:*

# Check queue status
docker exec bagisto-app php artisan queue:work --once
```

4. **Database Connection:**
```bash
# Test database connection
docker exec bagisto-app php artisan tinker --execute="DB::connection()->getPdo();"
```

### Logs
```bash
# Application logs
docker exec bagisto-app tail -f /var/www/html/storage/logs/laravel.log

# Nginx logs
docker exec bagisto-app tail -f /var/log/nginx/error.log

# Supervisor logs
docker exec bagisto-app tail -f /var/log/supervisor/supervisord.log
```

## Security Considerations

1. **Use secrets for sensitive data**
2. **Enable HTTPS with proper SSL certificates**
3. **Configure firewall rules**
4. **Regular security updates**
5. **Monitor access logs**
6. **Use non-root database users**
7. **Implement proper backup strategies**

## Performance Optimization

1. **Enable opcache in production**
2. **Use CDN for static assets**
3. **Configure proper caching headers**
4. **Monitor and optimize database queries**
5. **Use Redis for sessions and cache**
6. **Enable Gzip compression (already configured)**
