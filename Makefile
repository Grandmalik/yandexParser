COMPOSE := docker compose

.DEFAULT_GOAL := help
.PHONY: help up down logs shell check migrate seed fresh canary health

help: ## Show the available targets
	@grep -hE '^[a-z-]+:.*##' $(MAKEFILE_LIST) | sed -E 's/:.*## /\t/' | expand -t 12

up: ## Start the contour (app, worker, scheduler, reverb, nginx, node, postgres)
	$(COMPOSE) up -d

down: ## Stop the contour
	$(COMPOSE) down

shell: ## Open a shell in the application container
	$(COMPOSE) exec app sh

logs: ## Follow the logs
	$(COMPOSE) logs -f --tail=100

check: ## Everything CI runs: style, static analysis, tests, frontend
	composer check
	npm run lint && npm run format:check && npm run type-check && npm run test && npm run build

migrate: ## Run pending migrations
	$(COMPOSE) exec -T app php artisan migrate --force

seed: ## Create the single application user (DemoUserSeeder)
	$(COMPOSE) exec -T app php artisan db:seed --force

fresh: ## Rebuild the schema and reseed (destroys all data)
	$(COMPOSE) exec -T app php artisan migrate:fresh --seed --force

canary: ## Read the reference card live and report whether the parser still matches
	$(COMPOSE) exec -T app php artisan scraping:canary

health: ## Source health as an uptime check sees it
	$(COMPOSE) exec -T app php artisan tinker --execute="echo json_encode(app(App\Modules\Scraping\Application\Health\SourceHealth::class)->overall());"
