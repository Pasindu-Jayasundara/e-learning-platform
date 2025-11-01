# Copy this file to env.local.ps1 and fill in your real keys.
# This script sets environment variables for the current PowerShell session
# so the PHP app can read them via getenv('STRIPE_SECRET_KEY') etc.

# Stripe API keys
$env:STRIPE_SECRET_KEY = "sk_test_51SOTnkHhfobVBIN3uPz6edMa5m5LubUhw2IYLeoI5zac5mVY8WmOpmYMV66LePkC3p00voHe5prAs394232lNE7h00qqnuAnbe"
$env:STRIPE_PUBLISHABLE_KEY = "pk_test_51SOTnkHhfobVBIN3yOgNVEdtgm9o7z16OPYlsQweehdkReYnSQdAbi3OQoSrrMGRGwb3AwapFrxDWgWc0j5q5Xt200KZwJioQv"

# You can add more app config here if needed, e.g. database overrides:
# $env:DB_HOST = "127.0.0.1"
# $env:DB_NAME = "e_learning"
# $env:DB_USER = "root"
# $env:DB_PASS = "password"

# No output is produced intentionally. Dot-source this file to load:
# . .\scripts\env.local.ps1
