# PHP 8.3 + MariaDB (MySQL) environment for running the project in GitHub Codespaces.
FROM mcr.microsoft.com/devcontainers/php:1-8.3

RUN docker-php-ext-install pdo_mysql \
    && apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends mariadb-server mariadb-client \
    && rm -rf /var/lib/apt/lists/*
