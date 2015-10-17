help:
	make [composer|test|coverage|phpcs|server]

vendor/autoload.php:
	composer install

config.php:
	sed 's#// Add users here#"tester" => "$$2y$$10$$D.bYIPn1vxsLrd8FFOT7OOIYknwbYcO..AzhBGu2QuWjoZpLMIpoW"#' config.php.dist > config.php

vendor/bin/doctrine-dbal: vendor/autoload.php
vendor/bin/phpunit: vendor/autoload.php
vendor/bin/phpcs: vendor/autoload.php
vendor/bin/phpmd: vendor/autoload.php

composer: vendor/autoload.php

test: vendor/bin/phpunit
	vendor/bin/phpunit

coverage: vendor/bin/phpunit
	mkdir -p build
	vendor/bin/phpunit --coverage-clover build/clover.xml --coverage-html build/

phpcs: vendor/bin/phpcs
	vendor/bin/phpcs

phpmd: vendor/bin/phpmd
	mkdir -p build
	vendor/bin/phpmd src html cleancode,codesize,controversial,design,naming,unusedcode --reportfile build/phpmd.html

server: vendor/autoload.php config.php
	php -S 0.0.0.0:8000 -t web/
