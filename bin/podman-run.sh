#!/bin/sh

set -eu

action="up"
environment=""
detach=0
no_build=0

usage() {
    cat <<'EOF'
Usage: ./bin/podman-run.sh [options]

Options:
  -a, --action <up|down|logs|rebuild>
  -e, --environment <development|production>
  -d, --detach
      --no-build
  -h, --help
EOF
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        -a|--action)
            action="${2:-}"
            shift 2
            ;;
        -e|--environment)
            environment="${2:-}"
            shift 2
            ;;
        -d|--detach)
            detach=1
            shift
            ;;
        --no-build)
            no_build=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

case "$action" in
    up|down|logs|rebuild) ;;
    *)
        echo "Invalid action: $action" >&2
        exit 1
        ;;
esac

if [ -n "$environment" ]; then
    case "$environment" in
        development|production) ;;
        *)
            echo "Invalid environment: $environment" >&2
            exit 1
            ;;
    esac
fi

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
repo_root=$(CDPATH= cd -- "$script_dir/.." && pwd)
env_file="$repo_root/.env"

if [ ! -f "$env_file" ]; then
    echo "Missing .env file at $env_file" >&2
    exit 1
fi

read_env_value() {
    key="$1"
    awk -F= -v search_key="$key" '
        /^[[:space:]]*#/ { next }
        /^[[:space:]]*$/ { next }
        $1 == search_key {
            sub(/^[^=]*=/, "", $0)
            print $0
            exit
        }
    ' "$env_file"
}

get_setting() {
    key="$1"
    default_value="$2"
    value=$(read_env_value "$key")

    if [ -n "$value" ]; then
        printf '%s' "$value"
    else
        printf '%s' "$default_value"
    fi
}

if [ -z "$environment" ]; then
    environment=$(get_setting "ENVIRONMENT" "development")
fi

server_port=$(get_setting "SERVER_PORT" "8000")
image_tag=$(get_setting "DOCKER_IMAGE_TAG" "php-8.5.9-cli-trixie")
app_path=$(get_setting "APP_PATH" "/app")
image_name="localhost/dvictorjhg/braidphp:${image_tag}-${environment}"
container_name="braidphp-${environment}"

debug_dir="$repo_root/debug"
coverage_dir="$repo_root/coverage"

mkdir -p "$debug_dir" "$coverage_dir"

cd "$repo_root"

if [ "$action" = "up" ] || [ "$action" = "rebuild" ]; then
    if [ "$no_build" -eq 0 ]; then
        echo "Building $image_name from docker/php/php.Dockerfile ($environment target)..."
        podman build \
            -f docker/php/php.Dockerfile \
            --target "$environment" \
            --build-arg "APP_PATH=$app_path" \
            --build-arg "ENVIRONMENT=$environment" \
            -t "$image_name" \
            .
    fi
fi

case "$action" in
    down)
        exec podman rm -f "$container_name"
        ;;
    logs)
        exec podman logs -f "$container_name"
        ;;
    *)
        set -- run
        set -- "$@" --rm
        set -- "$@" --replace
        set -- "$@" --name "$container_name"
        set -- "$@" -p "${server_port}:${server_port}"
        set -- "$@" -e "ENVIRONMENT=$environment"
        set -- "$@" -e "SERVER_ADDRESS=0.0.0.0"
        set -- "$@" -e "SERVER_PORT=$server_port"
        set -- "$@" -v "$debug_dir:/tmp/xdebug"
        set -- "$@" -v "$coverage_dir:$app_path/coverage"

        if [ "$environment" = "development" ]; then
            set -- "$@" \
                -v "$repo_root/src:$app_path/src" \
                -v "$repo_root/Example:$app_path/Example" \
                -v "$repo_root/tests:$app_path/tests"
        fi

        if [ "$detach" -eq 1 ]; then
            set -- "$@" -d
        fi

        set -- "$@" "$image_name"

        echo "Starting $container_name on http://127.0.0.1:$server_port ..."
        exec podman "$@"
        ;;
esac
