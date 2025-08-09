#!/bin/bash

# Quick Stress Test - Simple one-liner runner

# Quick test with default settings (uses random wallets)
echo "🚀 Running quick stress test with random wallets..."
seq 1 50 | parallel -j 50 'php artisan wallet:stress-worker --operations=300 --process={} --type=mixed'

# Or if you don't have parallel installed, use xargs:
# seq 1 50 | xargs -P 50 -I {} php artisan wallet:stress-worker --operations=300 --process={} --type=mixed

# With specific wallet IDs (replace with your actual IDs)
# seq 1 50 | parallel -j 50 'php artisan wallet:stress-worker --operations=300 --process={} --wallets=1,2,3,4,5,6,7,8,9,10'

# Different test scenarios:

# 1. High-volume deposits only
# seq 1 100 | parallel -j 100 'php artisan wallet:stress-worker --operations=100 --process={} --type=deposit'

# 2. Withdrawal stress test
# seq 1 50 | parallel -j 50 'php artisan wallet:stress-worker --operations=200 --process={} --type=withdraw'

# 3. Transfer-only test
# seq 1 75 | parallel -j 75 'php artisan wallet:stress-worker --operations=150 --process={} --type=transfer'

# 4. Slow operations with delay
# seq 1 20 | parallel -j 20 'php artisan wallet:stress-worker --operations=500 --process={} --delay=10'

# 5. Maximum stress test (careful with this!)
# seq 1 200 | parallel -j 200 'php artisan wallet:stress-worker --operations=50 --process={}'
