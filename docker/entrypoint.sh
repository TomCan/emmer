#!/bin/sh

LISTEN=${LISTEN:-"0.0.0.0:8080"}

php -S $LISTEN -t public public/router.php
