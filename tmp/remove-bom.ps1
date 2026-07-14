$files = @(
    'C:\Users\Dev\Documents\Projects\01-practica-login\app\Http\Middleware\SecurityHeadersMiddleware.php',
    'C:\Users\Dev\Documents\Projects\01-practica-login\resources\views\layouts\app.blade.php',
    'C:\Users\Dev\Documents\Projects\01-practica-login\resources\views\layouts\guest.blade.php'
)
foreach ($f in $files) {
    $bytes = [System.IO.File]::ReadAllBytes($f)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $newBytes = $bytes[3..($bytes.Length-1)]
        [System.IO.File]::WriteAllBytes($f, $newBytes)
        Write-Host "BOM removed: $f"
    } else {
        Write-Host "No BOM: $f"
    }
}
