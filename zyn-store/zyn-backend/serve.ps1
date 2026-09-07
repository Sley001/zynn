param(
    [int]$Port = 8000,
    [string]$ListenAddress = '127.0.0.1'
)

$publicDirectory = Join-Path $PSScriptRoot 'public'
$routerPath = Join-Path $PSScriptRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
Push-Location -LiteralPath $publicDirectory
try {
    # CLI's built-in server does not load .user.ini. Apply upload limits here.
    & php -d upload_max_filesize=5M -d post_max_size=8M -S "${ListenAddress}:$Port" $routerPath
} finally {
    Pop-Location
}
