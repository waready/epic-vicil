param(
    [string]$Bucket = "archivo",
    [string]$Region = "sfo3",
    [string]$EnvPath = "backend\.env"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $EnvPath)) {
    throw "No se encontro $EnvPath. Ejecuta este script desde la raiz del proyecto."
}

$endpoint = "https://$Region.digitaloceanspaces.com"
$url = "https://$Bucket.$Region.digitaloceanspaces.com"

$accessKey = Read-Host "DigitalOcean Spaces Access Key"
$secureSecret = Read-Host "DigitalOcean Spaces Secret Key" -AsSecureString
$secretPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureSecret)

try {
    $secretKey = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretPtr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretPtr)
}

function Set-EnvValue {
    param(
        [string]$Content,
        [string]$Key,
        [string]$Value
    )

    $escapedValue = $Value -replace '\\', '\\' -replace '\$', '`$'
    if ($Content -match "(?m)^$Key=") {
        return [regex]::Replace($Content, "(?m)^$Key=.*$", "$Key=$escapedValue")
    }

    return $Content.TrimEnd() + "`r`n$Key=$Value`r`n"
}

$content = Get-Content -Path $EnvPath -Raw
$values = [ordered]@{
    FILESYSTEM_DISK = "s3"
    AWS_ACCESS_KEY_ID = $accessKey
    AWS_SECRET_ACCESS_KEY = $secretKey
    AWS_DEFAULT_REGION = $Region
    AWS_BUCKET = $Bucket
    AWS_URL = $url
    AWS_ENDPOINT = $endpoint
    AWS_USE_PATH_STYLE_ENDPOINT = "false"
    EVIDENCE_DIRECT_UPLOAD_ENABLED = "true"
    EVIDENCE_DIRECT_UPLOAD_DISK = "s3"
}

foreach ($item in $values.GetEnumerator()) {
    $content = Set-EnvValue -Content $content -Key $item.Key -Value $item.Value
}

Set-Content -Path $EnvPath -Value $content -NoNewline

Write-Host "Spaces configurado para bucket '$Bucket' en region '$Region'." -ForegroundColor Green
Write-Host "Ejecuta: cd backend; ..\tools\php-8.3\php.exe artisan config:clear" -ForegroundColor Yellow
