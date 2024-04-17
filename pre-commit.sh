#!/usr/bin/env bash

set -x

composer normalize
vendor/bin/ecs --fix
vendor/bin/phpstan analyse
vendor/bin/phpunit
