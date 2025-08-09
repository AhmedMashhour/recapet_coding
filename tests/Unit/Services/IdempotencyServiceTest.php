<?php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\IdempotencyService;
use App\Exceptions\ConcurrentRequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyService $idempotencyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->idempotencyService = new IdempotencyService();
    }

    public function test_idempotency_check_passes_for_new_key()
    {
        $key = Str::uuid()->toString();

        $result = $this->idempotencyService->checkIdempotent($key);

        $this->assertTrue($result);
    }

    public function test_idempotency_check_with_concurrent_requests()
    {
        $key = Str::uuid()->toString();

        // Mock cache lock to simulate concurrent request
        Cache::shouldReceive('lock')
            ->once()
            ->with("idempotency:{$key}", 100)
            ->andReturn(new class {
                public function get() { return false; }
            });

        $this->expectException(ConcurrentRequestException::class);

        $this->idempotencyService->checkIdempotent($key);
    }
}
