#!/usr/bin/env bash

if ! command -v act &> /dev/null
then
	echo "act could not be found"
	echo "Please install act by running brew install act or going to https://nektosact.com"
	exit 1
fi

exec act -P ubuntu-latest=shivammathur/node:latest
