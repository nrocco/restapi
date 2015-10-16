help:
	make [deps|test|coverage|server]

vendor/bin/doctrine-dbal:
	composer install --no-dev

vendor/bin/phpunit:
	composer install --dev

test: vendor/bin/phpunit
	vendor/bin/phpunit

coverage: vendor/bin/phpunit
	vendor/bin/phpunit --coverage-html build/

server: vendor/bin/doctrine-dbal
	php -S localhost:8000 -t web/
