# Testing

## Unit tests

```
./bin/phpunit
```

## Unit tests with database

```
export APP_ENV=test
php bin/console doctrine:schema:drop
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load
```
