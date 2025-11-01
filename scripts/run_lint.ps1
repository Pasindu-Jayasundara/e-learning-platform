# Run PHP lint for project PHP files (PowerShell)
Write-Host "Running php -l on public/*.php and includes/*.php"
$files = Get-ChildItem -Path "$PSScriptRoot\..\public" -Filter *.php -Recurse | Select-Object -ExpandProperty FullName
$files += Get-ChildItem -Path "$PSScriptRoot\..\includes" -Filter *.php -Recurse | Select-Object -ExpandProperty FullName
foreach ($f in $files) {
    Write-Host "Checking $f"
    & php -l "$f"
    if ($LASTEXITCODE -ne 0) { Write-Host "Syntax error in $f" -ForegroundColor Red; exit 1 }
}
Write-Host "All files passed php -l" -ForegroundColor Green
