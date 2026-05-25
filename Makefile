.PHONY: run up down init var ci bundle-install bundle-phpstan bundle-ecs bundle-phpunit bundle-tests bundle-fix

MAKEFLAGS += --no-print-directory # to disable "make: Entering directory ..." messages
export COMPOSE_FILE := compose.yml:compose.override.yaml

run: init

up:
	docker compose up -d

down:
	docker compose down

init:
	which docker > /dev/null || (echo "Please install docker binary" && exit 1)
	if command -v direnv &> /dev/null; then \
		cp --update=none .envrc.dist .envrc; \
		direnv allow; \
	fi
	docker compose up -d
	./bin-docker/composer install
	docker compose exec -T -u root php rm -rf "tests/Application/var/$(APP_ENV)"
	@make var
	./bin-docker/php ./bin/console doctrine:database:create --no-interaction --if-not-exists
	./bin-docker/php ./bin/console doctrine:migrations:migrate --no-interaction
	./bin-docker/php ./bin/console doctrine:schema:update --no-interaction --complete --force
	./bin-docker/php ./bin/console doctrine:migration:sync-metadata-storage
	./bin-docker/php ./bin/console assets:install
	./bin-docker/yarn --cwd=tests/Application install --pure-lockfile
	GULP_ENV=prod ./bin-docker/yarn --cwd=tests/Application build
	@make var

init-tests:
	which docker > /dev/null || (echo "Please install docker binary" && exit 1)
	if command -v direnv &> /dev/null; then \
		cp --update=none .envrc.dist .envrc; \
		direnv allow; \
	fi
	docker compose up -d
	./bin-docker/composer install
	@make var
	./bin-docker/php ./bin/console --env=test doctrine:database:drop --no-interaction --force --if-exists
	./bin-docker/php ./bin/console --env=test doctrine:database:create --no-interaction --if-not-exists
	./bin-docker/php ./bin/console --env=test doctrine:migrations:migrate --no-interaction
	./bin-docker/php ./bin/console --env=test doctrine:schema:update --no-interaction --complete --force
	./bin-docker/php ./bin/console --env=test doctrine:migration:sync-metadata-storage
	./bin-docker/php ./bin/console --env=test assets:install
	./bin-docker/yarn --cwd=tests/Application install --pure-lockfile
	GULP_ENV=prod ./bin-docker/yarn --cwd=tests/Application build
	@make var

cache:
	./bin-docker/php ./bin/console cache:clear
	@make var


static-only:
	@make ecs
	@make phpstan
	@make composer-lint
	@make symfony-lint
	@make doctrine-lint
	@make say-ok

phpstan:
	./bin-docker/php ./bin/console --env=test cache:warmup --no-optional-warmers
	./bin-docker/docker-bash bin/phpstan.sh

phpunit:
	./bin-docker/docker-bash bin/phpunit.sh --testdox

behat:
	./bin-docker/docker-bash bin/behat.sh

ecs:
	./bin-docker/docker-bash bin/ecs.sh

symfony-lint:
	./bin-docker/docker-bash bin/symfony-lint.sh

composer-lint:
	./bin-docker/composer validate

doctrine-lint:
	./bin-docker/docker-bash bin/doctrine-lint.sh

lint: symfony-lint composer-lint doctrine-lint

yarn-build:
	./bin-docker/yarn install
	./bin-docker/yarn build

make yarn: yarn-build

schema-reset:
	./bin-docker/php ./bin/console doctrine:database:drop --force --if-exists
	./bin-docker/php ./bin/console doctrine:database:create --no-interaction
	./bin-docker/php ./bin/console doctrine:migrations:migrate --no-interaction
	./bin-docker/php ./bin/console doctrine:schema:update --no-interaction --complete --force
	./bin-docker/php ./bin/console doctrine:migration:sync-metadata-storage

fix:
	./bin-docker/docker-bash bin/ecs.sh --fix

bare-fixtures:
	@echo "############\nLoading fixtures: $(SPEED_MESSAGE)\n############"
	./bin-docker/php ./bin/console sylius:fixtures:load --no-interaction

var:
	rm -rf tests/Application/var
	mkdir -p tests/Application/var/log
	touch tests/Application/var/log/test.log
	touch tests/Application/var/log/dev.log
	chmod -R 0777 tests/Application/var

fixtures: schema-reset bare-fixtures

static: phpstan ecs lint

tests: static behat bundle-tests

ci: init-tests tests

bundle-install:
	./bin-docker/docker-bash -c "cd packages/enterprise-security-bundle && composer install --no-interaction --no-progress"

bundle-phpstan: bundle-install
	./bin-docker/docker-bash -c "cd packages/enterprise-security-bundle && vendor/bin/phpstan analyse --memory-limit=1G --no-progress"

bundle-ecs: bundle-install
	./bin-docker/docker-bash -c "cd packages/enterprise-security-bundle && vendor/bin/ecs check"

bundle-fix: bundle-install
	./bin-docker/docker-bash -c "cd packages/enterprise-security-bundle && vendor/bin/ecs check --fix"

bundle-phpunit: bundle-install
	./bin-docker/docker-bash -c "cd packages/enterprise-security-bundle && vendor/bin/phpunit"

bundle-tests: bundle-phpstan bundle-ecs bundle-phpunit

say-ok:
	@echo "✅ OK ✅"

php-bash:
	./bin-docker/docker-bash

bash: php-bash
