#!/bin/sh

if [ "$1" != "" ]
then
    exec "$@"
else
    if [ "${INIT_KEY}" != "" ] && [ "${INIT_SECRET}" != "" ]
    then
        X=$(bin/console doctrine:schema:create)
        if [ $? -eq 0 ]
        then
            # schema create will fail if it already exists
            bin/console app:user:create -r ${INIT_USER:-emmer}
            bin/console app:user:create-access-key --access-key="${INIT_KEY}" --access-secret="${INIT_SECRET}" ${INIT_USER:-emmer}
        fi
    fi

    LISTEN=${LISTEN:-"0.0.0.0:8080"}
    exec php -S $LISTEN -t public public/router.php
fi
