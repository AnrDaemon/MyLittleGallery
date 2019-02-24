SHELL := /bin/sh
.SHELLFLAGS := -ec
.ONESHELL:

PHAR_FILES := index.php config.php Gallery.php

clean:
	-rm index.php config.php stub.php mlg.phar

release: release-phar

release-phar: mlg.phar

stub: stub.php

%.php: %.sample.php
	cp "$<" "$@"

stub.php: config.sample.php
	cp "config.sample.php" "stub.php"
	printf "\n%s" \
	  "require_once 'phar://' . __FILE__ . '/index.php';" "" \
	  "__HALT_COMPILER(); ?>" >> "stub.php"

mlg.phar: stub.php $(PHAR_FILES)
	phar pack -f "$@" -c bzip2 -s $^

.PHONY: clean stub release ;
