# Docker image for Tiny Tiny RSS

This [Docker](https://www.docker.com) image allows you to run the [Tiny Tiny RSS](http://tt-rss.org) feed reader. Keep your feed history to yourself and access your RSS and atom feeds from everywhere. You can access it through an easy to use webinterface on your desktop, your mobile browser or using one of the available apps.

## Changes from the original repository

This repository is a fork of [this repository](https://github.com/clue/docker-ttrss) and includes the following changes.

* Upgrade to PHP 7.3.
* Use Apache over nginx
* Rewrote the introduction and readme a bit.
* Rewrote the DB configuration script.
* Added a [License](LICENSE) (AGPL)
* [Image builds weekly on Travis CI](https://travis-ci.com/JC5/docker-ttrss/builds) to ensure Tiny Tiny RSS is up-to-date.
* Multi-arch support (see below)

## About Tiny Tiny RSS

From [the official README](https://git.tt-rss.org/fox/tt-rss):

> Web-based news feed aggregator, designed to allow you to read news from any location, while feeling as close to a real desktop application as possible.

## About multi arch support

[The image](https://hub.docker.com/r/jc5x/ttrss) supports the following CPU architectures:

* AMD64: This is plain old x86-64 and can be found in any modern PC. If you don't know what you're running yourself, it's x86-64.
* ARM: Also known as ARM 32-bit, this is the platform of most older Raspberry PI's, up to the RPi 2 (model 1.1)
* ARM64: It's the same ARM platform, but 64-bits. Is used in all modern Raspberry PI's.

## Quickstart

This section assumes you want to get started quickly, the following sections explain the steps in more detail. So let's start.

Just start up a new database container:

```bash
docker run -d --name ttrssdb postgres:latest
```

And because this docker image is available as an [image on Docker Hub](https://hub.docker.com/r/jc5x/ttrss), using it is as simple as launching this Tiny Tiny RSS installation linked to your fresh database:

```bash
docker run -d --link ttrssdb:db -p 80:80 jc5x/ttrss:latest
```

Running this command for the first time will download the image automatically.

### Accessing the web interface

The above example exposes the Tiny Tiny RSS webinterface on port 80, so that you can browse to:

http://localhost/

The default login credentials are:

* Username: admin
* Password: password

Obviously, you're recommended to change these as soon as possible.

## Installation Walkthrough

Having trouble getting the above to run? This is the detailed installation walkthrough. If you've already followed the [quickstart](#quickstart) guide and everything works, you can skip this part.

### Select database

This container requires a PostgreSQL or MySQL database instance.

Following docker's best practices, this container does not contain its own database, but instead expects you to supply a running instance. While slightly more complicated at first, this gives your more freedom as to which database instance and configuration you're relying on. Also, this makes this container quite disposable, as it doesn't store any sensitive information at all.

#### PostgreSQL container

The recommended way to run this container is by linking it to a PostgreSQL database instance. You're free to pick (or build) any PostgreSQL container, as long as it exposes its database port (5432) to the outside.

Example with `postgres:latest`:

```bash
docker run -d --name=tinydatabase postgres:latest
```

The image `postgres:latest` exposes a database superuser that this image uses to automatically create its user and database, so you don't have to setup your database credentials here.

Use the following database options when running the container:

```
--link tinydatabase:db
```

#### MySQL container

If you'd like to use ttrss with a mysql database backend, simply link it to a mysql container instead. You're free to pick (or build) any MySQL container, as long as it exposes its database port (3306) to the outside.

Example with `mariadb:latest`:

```bash
$ docker run -d --name=tinydatabase -e MYSQL_USER=ttrss -e MYSQL_PASSWORD=ttrss -e MYSQL_DATABASE=ttrss mariadb:latest
```

The image `mariadb:latest` does not expose a database superuser, so you have to explicitly pass the database credentials here.

Use the following database options when running the container:

```
--link tinydatabase:db
```

#### External database server

If you already have a PostgreSQL or MySQL server around off docker you also can go with that. Instead of linking docker containers you need to provide database hostname and port like so:

```
-e DB_HOST=172.17.42.1
-e DB_PORT=3306
```

### Database configuration

Whenever your run ttrss, it will check your database setup. It assumes the following default configuration, which can be changed by passing the following additional arguments:

```
-e DB_NAME=ttrss
-e DB_USER=ttrss
-e DB_PASS=ttrss
```

If your database is exposed on a non-standard port you also need to provide DB_TYPE set to either "pgsql" or "mysql".

```
-e DB_TYPE=pgsql
-e DB_TYPE=mysql
```

### Database superuser

When you run ttrss, it will check your database setup. If it can not connect using the above configuration, it will automatically try to create a new database and user.

For this to work, it will need a superuser account that is permitted to create a new database and user. It assumes the following default configuration, which can be changed by passing the following additional arguments:

```
-e DB_ENV_USER=docker
-e DB_ENV_PASS=docker
```

### SELF_URL_PATH

The `SELF_URL_PATH` config value should be set to the URL where this TinyTinyRSS will be accessible at. Setting it correctly will enable PUSH support and make the browser integration work. Default value: `http://localhost`.

For more information check out the [official documentation](https://git.tt-rss.org/fox/tt-rss/src/master/config.php-dist#L21).

```
-e SELF_URL_PATH=https://example.org/ttrss
```

### Testing ttrss in foreground

For testing purposes it's recommended to initially start this container in foreground. This is particular useful for your initial database setup, as errors get reported to the console and further execution will halt.

```bash
docker run -it --link tinydatabase:db -p 80:80 jc5x/ttrss:latest
```

### Running ttrss daemonized

Once you've confirmed everything works in the foreground, you can start your container in the background by replacing the `-it` argument with `-d` (daemonize). Remaining arguments can be passed just like before, the following is the recommended minimum:

```bash
docker run -d --link tinydatabase:db -p 80:80 jc5x/ttrss:latest
```
