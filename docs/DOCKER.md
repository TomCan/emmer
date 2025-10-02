# Emmer in docker

## Build

This repository comes with a Dockerfile to build a docker container containing Emmer. It provides and entrypoint script
that will launch the PHP Built-in webserver with a router file. This can be used for testing purposes, but for anything
serious a PHP + Apache or PHP-FPM image is recommended.

You can use the pre-built images from the `tomcan/emmer` [repository at Docker hub](https://hub.docker.com/r/tomcan/emmer).

To build the container yourself, you can run following docker command (obviously specify your desired tag)
```
docker build -t emmer:my-tag -f docker/Dockerfile .
```

## Running the container

To run the container, simply start it without parameters. 

```
docker run emmer:my-tag
```

The `entrypoint` script provides the option to automatically create the database schema with a root user and access-key
and secret. The script will try and use `doctrine:schema:create` to create the actual database schema, which should fail
if it already exists. If it succeeds in creating the schema, a user is created with the provided access key and secret.

Following will use a sqlite database under var. If it doesn't exist, it will create it with a new user and provided access keys.
This can be usefull in cicd or testing scenario's.
```
docker run -ti -e "INIT_KEY=EMRMYACCESSKEY" -e "INIT_SECRET=MYSECRET" -e "DATABASE_URL=sqlite:///app/var/emmer.db" emmer:my-tag
```

### Volumes / Persistence

Buckets are stored under the /app/var/storage service. You should mount this as a docker volume if you need to retain the
data when the container is deleted. If you're using an sqlite database that needs to be retained, you should also put that
on a docker volume outside of the container. 

```
docker run -ti -v "/path/to/the/buckets:/app/var/storage" emmer:my-tag
```
