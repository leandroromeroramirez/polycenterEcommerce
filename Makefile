# Bagisto Docker Management Makefile

.PHONY: help build up down restart logs shell clean dev prod install

# Default environment
ENV ?= dev
COMPOSE_FILE = docker-compose.yml
APP_SERVICE = laravel.test
ifeq ($(ENV),prod)
    COMPOSE_FILE = docker-compose.prod.yml
    APP_SERVICE = app
endif

help: ## Show this help message
	@echo 'Usage: make [target] [ENV=dev|prod]'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build Docker images
	docker-compose -f $(COMPOSE_FILE) build

up: ## Start all services
	docker-compose -f $(COMPOSE_FILE) up -d

down: ## Stop all services
	docker-compose -f $(COMPOSE_FILE) down

restart: down up ## Restart all services

logs: ## Show logs from all services
	docker-compose -f $(COMPOSE_FILE) logs -f

logs-app: ## Show logs from app service only
	docker-compose -f $(COMPOSE_FILE) logs -f $(APP_SERVICE)

shell: ## Open shell in the app container
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) bash

shell-root: ## Open root shell in the app container
	docker-compose -f $(COMPOSE_FILE) exec --user root $(APP_SERVICE) bash

mysql: ## Open MySQL shell
	docker-compose -f $(COMPOSE_FILE) exec mysql mysql -u$${DB_USERNAME:-bagisto} -p$${DB_PASSWORD:-secret} $${DB_DATABASE:-bagisto}

redis: ## Open Redis CLI
	docker-compose -f $(COMPOSE_FILE) exec redis redis-cli

clean: ## Remove all containers, volumes, and images
	docker-compose -f $(COMPOSE_FILE) down -v --rmi all
	docker system prune -f

dev: ## Start development environment
	cp .env.docker .env
	make ENV=dev up

prod: ## Start production environment
	cp .env.docker .env
	make ENV=prod up

install: ## Install Bagisto (run after first startup)
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan key:generate
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan migrate
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan db:seed
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan storage:link
	@echo "Bagisto installation completed!"
	@echo "Access your application at: http://localhost"
	@echo "Admin panel: http://localhost/admin"

reset: ## Reset database and cache
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan migrate:refresh --seed
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan cache:clear
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan config:clear
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan route:clear
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan view:clear

optimize: ## Optimize application for production
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan config:cache
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan route:cache
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan view:cache
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan optimize

backup: ## Create database backup
	@echo "Creating database backup..."
	docker-compose -f $(COMPOSE_FILE) exec mysql mysqldump -u$${DB_USERNAME:-bagisto} -p$${DB_PASSWORD:-secret} $${DB_DATABASE:-bagisto} > backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backup created: backup_$$(date +%Y%m%d_%H%M%S).sql"

restore: ## Restore database from backup (usage: make restore FILE=backup.sql)
ifndef FILE
	@echo "Please specify backup file: make restore FILE=backup.sql"
else
	docker-compose -f $(COMPOSE_FILE) exec -T mysql mysql -u$${DB_USERNAME:-bagisto} -p$${DB_PASSWORD:-secret} $${DB_DATABASE:-bagisto} < $(FILE)
	@echo "Database restored from $(FILE)"
endif

status: ## Show status of all services
	docker-compose -f $(COMPOSE_FILE) ps

health: ## Check health of all services
	@echo "Checking application health..."
	@curl -f http://localhost/health && echo "✅ Application is healthy" || echo "❌ Application is not healthy"
	@echo "Checking database..."
	@docker-compose -f $(COMPOSE_FILE) exec mysql mysqladmin ping -h localhost -u$${DB_USERNAME:-bagisto} -p$${DB_PASSWORD:-secret} && echo "✅ Database is healthy" || echo "❌ Database is not healthy"
	@echo "Checking Redis..."
	@docker-compose -f $(COMPOSE_FILE) exec redis redis-cli ping && echo "✅ Redis is healthy" || echo "❌ Redis is not healthy"

update: ## Update application dependencies
	docker-compose -f $(COMPOSE_FILE) exec app composer update
	docker-compose -f $(COMPOSE_FILE) exec app npm update
	docker-compose -f $(COMPOSE_FILE) exec app npm run build

test: ## Run tests
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan test

npm: ## Run npm commands (usage: make npm CMD="install")
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) npm $(CMD)

artisan: ## Run artisan commands (usage: make artisan CMD="make:controller TestController")
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) php artisan $(CMD)

composer: ## Run composer commands (usage: make composer CMD="require package/name")
	docker-compose -f $(COMPOSE_FILE) exec $(APP_SERVICE) composer $(CMD)
