#!/usr/bin/env bash

set -ex

#vendor/bin/ecs --fix
vendor/bin/phpstan analyse
vendor/bin/phpunit
