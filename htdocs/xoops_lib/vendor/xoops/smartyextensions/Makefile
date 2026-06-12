.PHONY: install test analyse lint fix baseline ci help

# ──────────────────────────────────────────────────────────────────────────────
# Development workflow for xoops/smartyextensions
# Usage: make <target>
# ──────────────────────────────────────────────────────────────────────────────

help:          ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

install:       ## Install Composer dependencies (run this first after cloning)
	composer install

test:          ## Run the PHPUnit test suite
	composer test

analyse:       ## Run PHPStan static analysis
	composer analyse

lint:          ## Run PHP_CodeSniffer code style check
	composer lint

fix:           ## Run PHPCBF to auto-fix code style issues
	composer fix

baseline:      ## Regenerate PHPStan baseline — run once on first checkout before using 'analyse' as a gate
	./vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon

ci:            ## Run the full CI pipeline locally (install → lint → analyse → test)
	$(MAKE) install
	$(MAKE) lint
	$(MAKE) analyse
	$(MAKE) test
