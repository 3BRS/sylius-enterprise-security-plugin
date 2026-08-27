.PHONY: run up down init var ci

MAKEFLAGS += --no-print-directory # to disable "make: Entering directory ..." messages

run: init

up:
	docker compose up -d

down:
	docker compose down

init:
	which docker > /dev/null || (echo "Please install docker binary" && exit 1)
	if command -v direnv &> /dev/null; then \
		[ -f .envrc ] || cp .envrc.dist .envrc; \
		direnv allow; \
	fi
	[ -f compose.override.yaml ] || cp compose.override.dist.yaml compose.override.yaml
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
		[ -f .envrc ] || cp .envrc.dist .envrc; \
		direnv allow; \
	fi
	[ -f compose.override.yaml ] || cp compose.override.dist.yaml compose.override.yaml
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

tests: static behat

ci: init-tests tests

say-ok:
	@echo "✅ OK ✅"

php-bash:
	./bin-docker/docker-bash

bash: php-bash
