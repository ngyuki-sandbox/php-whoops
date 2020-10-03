
.PHONY: commands
commands:
	@grep -Po '^[a-z][^:\s]+' < Makefile | sed -e 's/^/make /'

.PHONY: dev
dev:
	tmux-cmds make up :: make bs

.PHONY: up
up:
	xdebug -S 0.0.0.0:9876 -d zend.exception_ignore_args=0 example/index.php

.PHONY: bs
bs:
	browser-sync start --proxy localhost:9876 -f example/

.PHONY: psalm
psalm: psalm-config
	vendor/bin/psalm.phar --threads 1 --php-version=7.0 --diff

.PHONY: psalm-config
psalm-config:
	rm -f psalm.xml
	vendor/bin/psalm.phar --init src/ 3

.PHONY: qa
qa: psalm
