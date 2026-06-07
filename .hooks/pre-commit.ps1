$files = git diff --cached --name-only --diff-filter=ACM | Select-String '\.php$' | ForEach-Object { $_.Line }

if (-not $files) {
    Write-Host "No PHP files changed. Skipping Pint & Larastan."
    exit 0
}

Write-Host "Running Pint (auto-fix on changed files)..."
vendor/bin/pint $files
if ($LASTEXITCODE -ne 0) { exit 1 }

Write-Host "Re-staging files after Pint..."
git add $files

Write-Host "Running Larastan..."
vendor/bin/larastan analyse --memory-limit=2G
if ($LASTEXITCODE -ne 0) { exit 1 }
