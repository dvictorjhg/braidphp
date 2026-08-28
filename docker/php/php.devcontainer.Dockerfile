# base
FROM docker.io/library/php:8.5.9-cli-alpine3.24 AS base

COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# dependencies
FROM base AS dependencies
COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --chmod=0755 docker/php/run-composer.sh /usr/local/bin/run-composer.sh
COPY --chmod=0755 docker/php/php.devcontainer.docker-entrypoint.sh /usr/local/bin/php.devcontainer.docker-entrypoint.sh

# development
FROM dependencies AS development
USER root
COPY docker/php/xdebug.ini $PHP_INI_DIR/conf.d/xdebug.ini

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    ## Install the packages needed for development
    && apk update && apk upgrade \
    && apk add --no-cache \
    $PHPIZE_DEPS \
    git \
    jq \
    linux-headers \
    nodejs \
    npm \
    openjdk21 \
    shadow \
    sudo \
    zsh \
    zsh-autosuggestions \
    zsh-syntax-highlighting \
    zsh-vcs \
    ## Install php extensions
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    ## Change default shell to zsh for www-data user and add sudo permissions
    && chsh -s /bin/zsh www-data \
    && echo 'www-data ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

## Run everything after as non-privileged user
USER www-data

## Install ohmyzsh
RUN sh -c "$(wget https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh -O -)" \
    && echo "source /usr/share/zsh/plugins/zsh-syntax-highlighting/zsh-syntax-highlighting.zsh" >> ~/.zshrc \
    && echo "source /usr/share/zsh/plugins/zsh-autosuggestions/zsh-autosuggestions.zsh" >> ~/.zshrc \
    ### Customize ohmyzsh theme: https://github.com/ohmyzsh/ohmyzsh/wiki/Themes
    && sed -i 's/ZSH_THEME="robbyrussell"/ZSH_THEME="agnoster"/g' ~/.zshrc

WORKDIR ${APP_PATH}

CMD ["sh", "-c", "trap 'exit 0' SIGTERM; while true; do sleep 1; done"]
ENTRYPOINT [ "php.devcontainer.docker-entrypoint.sh" ]
