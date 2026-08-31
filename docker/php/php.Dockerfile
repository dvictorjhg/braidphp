# base
FROM docker.io/library/php:8.5.9-cli-trixie AS base
ARG ENVIRONMENT
ENV ENVIRONMENT=${ENVIRONMENT}
ARG APP_PATH
ENV APP_PATH=${APP_PATH}

COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# dependencies
FROM base AS dependencies
## Install the necessary packages for the run-composer.sh script
RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
    zsh \
    jq \
    unzip \
    && rm -rf /var/lib/apt/lists/*
USER www-data
COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --chmod=0755 docker/php/run-composer.sh /usr/local/bin/run-composer.sh
WORKDIR ${APP_PATH}
COPY --chown=www-data composer.json ./
## Install project dependencies with Composer
RUN run-composer.sh

# development
FROM base AS development

## Use root user for configure and install packages for the development environment
USER root

COPY --chmod=0755 docker/php/php.dev.docker-entrypoint.sh /usr/local/bin/php.dev.docker-entrypoint.sh
COPY docker/php/xdebug.ini $PHP_INI_DIR/conf.d/xdebug.ini

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    ## Install the packages needed for development
    && apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    git \
    linux-libc-dev \
    passwd \
    sudo \
    zsh-autosuggestions \
    zsh-syntax-highlighting \
    wget \
    && rm -rf /var/lib/apt/lists/* \
    ## Install php extensions
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    ## Change default shell to zsh for www-data user and add sudo permissions
    && chsh -s /bin/zsh www-data \
    && echo 'www-data ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers \
    && chown www-data:www-data /var/www

## Run everything after as non-privileged user
USER www-data

## Install ohmyzsh
RUN sh -c "$(wget https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh -O -)" \
    && echo "source /usr/share/zsh/plugins/zsh-syntax-highlighting/zsh-syntax-highlighting.zsh" >> ~/.zshrc \
    && echo "source /usr/share/zsh/plugins/zsh-autosuggestions/zsh-autosuggestions.zsh" >> ~/.zshrc \
    ### Customize ohmyzsh theme: https://github.com/ohmyzsh/ohmyzsh/wiki/Themes
    && sed -i 's/ZSH_THEME="robbyrussell"/ZSH_THEME="agnoster"/g' ~/.zshrc

WORKDIR ${APP_PATH}

## Copy project files and dependencies
COPY --chown=www-data src/ ./src/
COPY --chown=www-data Example/ ./Example/
COPY --chown=www-data tests/ ./tests/
COPY --from=dependencies --chown=www-data ${APP_PATH} ./

CMD ["php", "Example/index.php"]
ENTRYPOINT [ "php.dev.docker-entrypoint.sh" ]

# production
FROM base AS production

## Use root user for configure and install packages for the production environment
USER root

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

## Run everything after as non-privileged user
USER www-data

WORKDIR ${APP_PATH}

COPY --chown=www-data src/ ./src/
COPY --chown=www-data Example/ ./Example/
COPY --from=dependencies --chown=www-data ${APP_PATH} ./

CMD ["php", "Example/index.php"]
