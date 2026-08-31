param(
    [ValidateSet('up', 'down', 'logs', 'rebuild')]
    [string]$Action = 'up',

    [ValidateSet('development', 'production')]
    [string]$Environment = '',

    [switch]$Detach,

    [switch]$NoBuild
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Read-EnvFile {
    param([string]$Path)

    $values = @{}

    foreach ($line in Get-Content -Path $Path) {
        $trimmed = $line.Trim()

        if ($trimmed.Length -eq 0 -or $trimmed.StartsWith('#')) {
            continue
        }

        $name, $value = $trimmed -split '=', 2
        if ($name) {
            $values[$name.Trim()] = ($value ?? '').Trim()
        }
    }

    return $values
}

function Get-Setting {
    param(
        [hashtable]$Settings,
        [string]$Name,
        [string]$Default
    )

    if ($Settings.ContainsKey($Name) -and $Settings[$Name] -ne '') {
        return $Settings[$Name]
    }

    return $Default
}

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$envFile = Join-Path $repoRoot '.env'

if (-not (Test-Path -Path $envFile)) {
    throw "Missing .env file at $envFile"
}

$settings = Read-EnvFile -Path $envFile

if (-not $Environment) {
    $Environment = Get-Setting -Settings $settings -Name 'ENVIRONMENT' -Default 'development'
}

$serverPort = Get-Setting -Settings $settings -Name 'SERVER_PORT' -Default '8000'
$imageTag = Get-Setting -Settings $settings -Name 'DOCKER_IMAGE_TAG' -Default 'php-8.5.9-cli-trixie'
$appPath = Get-Setting -Settings $settings -Name 'APP_PATH' -Default '/app'
$imageName = "localhost/dvictorjhg/braidphp:$imageTag-$Environment"
$containerName = "braidphp-$Environment"

$debugDir = Join-Path $repoRoot 'debug'
$coverageDir = Join-Path $repoRoot 'coverage'

New-Item -ItemType Directory -Force -Path $debugDir | Out-Null
New-Item -ItemType Directory -Force -Path $coverageDir | Out-Null

Push-Location $repoRoot

try {
    if ($Action -in @('up', 'rebuild') -and -not $NoBuild) {
        Write-Host "Building $imageName from docker/php/php.Dockerfile ($Environment target)..."
        podman build `
            -f docker/php/php.Dockerfile `
            --target $Environment `
            --build-arg "APP_PATH=$appPath" `
            --build-arg "ENVIRONMENT=$Environment" `
            -t $imageName `
            .

        if ($LASTEXITCODE -ne 0) {
            exit $LASTEXITCODE
        }
    }

    switch ($Action) {
        'down' {
            podman rm -f $containerName
            exit $LASTEXITCODE
        }
        'logs' {
            podman logs -f $containerName
            exit $LASTEXITCODE
        }
        default {
            $runArgs = @(
                'run',
                '--rm',
                '--replace',
                '--name', $containerName,
                '-p', "${serverPort}:${serverPort}",
                '-e', "ENVIRONMENT=$Environment",
                '-e', 'SERVER_ADDRESS=0.0.0.0',
                '-e', "SERVER_PORT=$serverPort",
                '-v', "${debugDir}:/tmp/xdebug",
                '-v', "${coverageDir}:${appPath}/coverage"
            )

            if ($Environment -eq 'development') {
                $runArgs += @(
                    '-v', "$(Join-Path $repoRoot 'src'):${appPath}/src",
                    '-v', "$(Join-Path $repoRoot 'Example'):${appPath}/Example",
                    '-v', "$(Join-Path $repoRoot 'tests'):${appPath}/tests"
                )
            }

            if ($Detach) {
                $runArgs += '-d'
            }

            $runArgs += $imageName

            Write-Host "Starting $containerName on http://127.0.0.1:$serverPort ..."
            podman @runArgs
            exit $LASTEXITCODE
        }
    }
}
finally {
    Pop-Location
}
