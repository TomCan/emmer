FROM php:8.4-alpine

# Create non-root user
RUN addgroup emmer && adduser -G emmer -D emmer

ADD --chmod=775 https://getcomposer.org/composer.phar /usr/local/bin/composer
ADD --chown=emmer:emmer . /app

USER emmer
WORKDIR /app
RUN \
    echo "APP_ENV=prod" > .env.local \
    && composer install --no-dev -o

EXPOSE 8080
ENTRYPOINT [ "/app/docker/entrypoint.sh" ]
CMD [""]

