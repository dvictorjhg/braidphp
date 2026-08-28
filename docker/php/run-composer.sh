#!/bin/zsh
set -euo pipefail
IFS=$'\n\t'

PROGNAME=$(basename "$0")

echo "Executing ${PROGNAME} with ENVIRONMENT=${ENVIRONMENT}"

error() {
    local parent_lineno="$1"
    local message="${2:-Unexpected error}"
    local code="${3:-1}"
    if [[ -n "$message" ]]; then
        echo "Error on or near line ${parent_lineno}: ${message}; exiting with status ${code}" >&2
    else
        echo "Error on or near line ${parent_lineno}; exiting with status ${code}" >&2
    fi
    exit "$code"
}

trap 'error ${LINENO}' ERR


if [[ -f composer.json ]]; then
    if [[ "${ENVIRONMENT}" == "development" || "${ENVIRONMENT}" == "production" ]]; then
        psr4=($(jq -r '."autoload"."psr-4" | values[] | if type == "array" then .[] else . end' composer.json))
        psr4dev=($(jq -r '."autoload-dev"."psr-4" | values[] | if type == "array" then .[] else . end' composer.json))

        if [[ ${#psr4[@]} -eq 0 && ${#psr4dev[@]} -eq 0 ]]; then
            error "${LINENO}" "No psr-4 found in composer.json"
        fi

        if [[ "${ENVIRONMENT}" == "development" ]]; then
            psr4folders=("${psr4[@]}" "${psr4dev[@]}")
        else
            psr4folders=("${psr4[@]}")
        fi

        echo "PSR-4 folders: ${psr4folders[@]}"

        new_psr4folders+=()

        for folder in "${psr4folders[@]}"; do
            if [ ! -d "${folder}" ]; then
                mkdir -p "${folder}"
                new_psr4folders+=("${folder}")
            fi
        done

        if [[ "${ENVIRONMENT}" == "development" ]]; then
            composer update --no-cache
        else
            composer install --no-cache --no-dev
        fi

        for folder in "${new_psr4folders[@]}"; do
            rmdir "${folder}"
        done
    else
        error "${LINENO}" "Unknown environment: ${ENVIRONMENT}"
    fi
else
    error "${LINENO}" "composer.json not found"
fi

echo "Exiting ${PROGNAME}"
