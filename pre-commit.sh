#!/usr/bin/env bash

set -x

vendor/bin/ecs --fix
vendor/bin/phpstan analyse
vendor/bin/phpunit
