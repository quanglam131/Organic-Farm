<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tuần 5 checklist: FEFO allocation, shortage exception, and the global JSON error shape. */
class FefoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => uniqid().'@test.local',
            'password' => 'password-123',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /** A: 3 kg expiring in 2 days, B: 7 kg expiring in 9 days, C: expired, D: zero stock. */
    private function assignmentStock(): Product
    {
        $product = Product::factory()->create();

        ProductBatch::factory()->for($product)->expiringIn(2, 3)->create(['batch_code' => 'A']);
        ProductBatch::factory()->for($product)->expiringIn(9, 7)->create(['batch_code' => 'B']);
        ProductBatch::factory()->for($product)->expired(5)->create(['batch_code' => 'C']);
        ProductBatch::factory()->for($product)->expiringIn(5, 4)->depleted()->create(['batch_code' => 'D']);

        return $product;
    }

    public function test_buying_10kg_takes_3kg_from_the_earliest_batch_then_7kg_from_the_next(): void
    {
        $product = $this->assignmentStock();

        $plan = app(InventoryService::class)->allocateFefo($product, 10);

        $this->assertSame(['A', 'B'], collect($plan)->map(fn ($line) => $line['batch']->batch_code)->all());
        $this->assertSame([3, 7], collect($plan)->pluck('quantity')->all());
    }

    public function test_expired_and_zero_stock_batches_are_never_in_the_plan(): void
    {
        $product = $this->assignmentStock();

        $codes = collect(app(InventoryService::class)->allocateFefo($product, 10))
            ->map(fn ($line) => $line['batch']->batch_code);

        $this->assertFalse($codes->contains('C'));
        $this->assertFalse($codes->contains('D'));
    }

    public function test_batch_expiring_today_is_not_sellable(): void
    {
        $product = Product::factory()->create();
        ProductBatch::factory()->for($product)->expiringIn(0, 5)->create();

        $this->expectException(DomainException::class);
        app(InventoryService::class)->allocateFefo($product, 1);
    }

    public function test_shortage_below_the_requested_quantity_throws_an_exception(): void
    {
        // Only 8 kg is valid (3 + 5); the 4 kg expired batch must not count.
        $product = Product::factory()->create(['unit' => 'kg']);
        ProductBatch::factory()->for($product)->expiringIn(2, 3)->create();
        ProductBatch::factory()->for($product)->expiringIn(9, 5)->create();
        ProductBatch::factory()->for($product)->expired(4)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('thiếu 2 kg');
        app(InventoryService::class)->allocateFefo($product, 10);
    }

    public function test_fefo_preview_endpoint_returns_the_plan_and_the_shortage_error_envelope(): void
    {
        $product = $this->assignmentStock();
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/products/{$product->id}/fefo-preview?quantity=10")
            ->assertOk()
            ->assertJsonPath('plan.0.batch_code', 'A')
            ->assertJsonPath('plan.0.quantity', 3)
            ->assertJsonPath('plan.1.batch_code', 'B')
            ->assertJsonPath('plan.1.quantity', 7);

        $this->getJson("/api/admin/products/{$product->id}/fefo-preview?quantity=11")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message']);
    }

    public function test_api_errors_share_one_json_shape(): void
    {
        $this->getJson('/api/products/khong-co-san-pham')
            ->assertNotFound()
            ->assertJsonPath('status', 'error');

        $product = Product::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/batches", [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message', 'errors']);
    }

    public function test_anonymous_api_request_gets_a_401_in_the_same_shape(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');
    }

    public function test_qr_endpoint_returns_an_svg_for_a_batch(): void
    {
        $batch = ProductBatch::factory()->for(Product::factory())->expiringIn(5, 4)->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/admin/batches/{$batch->id}/qr")
            ->assertOk();

        $this->assertStringStartsWith('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }
}
