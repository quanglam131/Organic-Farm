<?php

namespace Tests\Feature;

use App\Models\FarmPlot;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rules from the course documents that span several modules:
 * the four roles (Tuần 2/7), order lines per batch (Tuần 3/5), complaints within 24 h with a
 * mandatory photo (Tuần 2/6), shipping by address (Tuần 6), admin alert settings and reviews.
 */
class FarmOperationsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** What the fake geocoder answers. */
    private array $geocode = [];

    private const ADDRESS = [
        'shipping_name' => 'Trần Thị B',
        'shipping_phone' => '0912345678',
        'shipping_address' => '12 Phan Đình Phùng, Đà Lạt',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.maps.google_key' => null,
            'cloudinary.cloud_name' => null, 'cloudinary.api_key' => null, 'cloudinary.api_secret' => null,
        ]);
        // Geocoder stub: finds nothing unless a test sets $geocode.
        Http::fake(fn () => Http::response($this->geocode, 200));
        Storage::fake('public');
    }

    private function user(string $role = 'customer'): User
    {
        return User::create([
            'name' => ucfirst($role).' '.uniqid(),
            'email' => uniqid().'@test.local',
            'password' => 'password-123',
            'role' => $role,
        ]);
    }

    private int $stockedProducts = 0;

    /** Lot A: 3 kg expiring in 2 days, lot B: 7 kg expiring in 9 days (codes LOT-A / LOT-B for the first product). */
    private function stockedProduct(): Product
    {
        $suffix = $this->stockedProducts++ === 0 ? '' : '-'.$this->stockedProducts;

        $product = Product::factory()->create(['price' => 40000, 'unit' => 'kg']);
        ProductBatch::factory()->for($product)->expiringIn(2, 3)->create(['batch_code' => 'LOT-A'.$suffix]);
        ProductBatch::factory()->for($product)->expiringIn(9, 7)->create(['batch_code' => 'LOT-B'.$suffix]);

        return $product;
    }

    private function deliveredOrder(User $customer, int $hoursAgo): Order
    {
        $service = app(OrderService::class);
        $order = $service->place($customer, [['product' => $this->stockedProduct(), 'quantity' => 2]], self::ADDRESS);
        $service->updateStatus($order, Order::STATUS_CONFIRMED);
        $service->updateStatus($order, Order::STATUS_SHIPPING);
        $service->updateStatus($order, Order::STATUS_DELIVERED);
        $order->update(['delivered_at' => now()->subHours($hoursAgo)]);

        return $order->fresh();
    }

    private function photo(string $name = 'bang-chung.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    // ----- Roles -----

    public function test_farm_staff_manage_batches_but_never_see_orders_customers_or_revenue(): void
    {
        $staff = $this->user('staff');
        $product = $this->stockedProduct();

        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/batches')->assertOk();
        $this->postJson("/api/admin/products/{$product->id}/batches", [
            'quantity_received' => 5,
            'harvested_on' => now()->toDateString(),
            'expires_on' => now()->addDays(5)->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/admin/orders')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/reports/revenue')->assertForbidden();
        $this->getJson('/api/admin/complaints')->assertForbidden();

        // Alerts for staff contain batches only, no order or customer data.
        $this->getJson('/api/admin/alerts')
            ->assertOk()
            ->assertJsonMissingPath('stock_issue_orders')
            ->assertJsonMissingPath('short_deliveries');
    }

    public function test_sales_handle_orders_but_cannot_create_batches_or_change_stock(): void
    {
        $sales = $this->user('sales');
        $product = $this->stockedProduct();
        $batch = $product->batches()->first();

        $this->actingAs($sales, 'sanctum')->getJson('/api/admin/orders')->assertOk();
        $this->getJson('/api/admin/complaints')->assertOk();
        $this->getJson('/api/admin/alerts')->assertOk()->assertJsonStructure(['expiring_batches', 'stock_issue_orders', 'short_deliveries']);

        $this->postJson("/api/admin/products/{$product->id}/batches", [
            'quantity_received' => 5,
            'harvested_on' => now()->toDateString(),
            'expires_on' => now()->addDays(5)->toDateString(),
        ])->assertForbidden();
        $this->postJson("/api/admin/batches/{$batch->id}/waste", ['quantity' => 1, 'reason' => 'x'])->assertForbidden();
        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();
    }

    public function test_customers_cannot_reach_any_back_office_endpoint_and_admins_reach_all(): void
    {
        $product = $this->stockedProduct();
        $batch = $product->batches()->first();

        $this->actingAs($this->user(), 'sanctum');
        foreach (['/api/admin/alerts', '/api/admin/batches', '/api/admin/orders', '/api/admin/dashboard'] as $url) {
            $this->getJson($url)->assertForbidden()->assertJsonPath('status', 'error');
        }
        $this->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();

        $this->actingAs($this->user('admin'), 'sanctum');
        foreach (['/api/admin/alerts', '/api/admin/batches', '/api/admin/orders', '/api/admin/dashboard', '/api/admin/settings'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson("/api/admin/batches/{$batch->id}")->assertOk()->assertJsonPath('batch_code', 'LOT-A');
    }

    public function test_locked_employee_is_refused(): void
    {
        $staff = $this->user('staff');
        $staff->update(['is_active' => false]);

        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/batches')->assertForbidden();
    }

    public function test_admin_creates_staff_accounts_with_hashed_passwords(): void
    {
        $this->actingAs($this->user('admin'), 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'Nhân viên vườn',
                'email' => 'vuon@farm.test',
                'role' => 'staff',
                'password' => 'mat-khau-123',
            ])
            ->assertCreated()
            ->assertJsonPath('role', 'staff')
            ->assertJsonMissingPath('password');

        $stored = User::where('email', 'vuon@farm.test')->value('password');
        $this->assertStringStartsWith('$2y$', $stored); // Bcrypt
    }

    // ----- Orders: one line per batch, stock issues -----

    public function test_confirming_10kg_records_3kg_from_lot_a_and_7kg_from_lot_b_in_the_order_details(): void
    {
        $customer = $this->user();
        $product = $this->stockedProduct();
        $service = app(OrderService::class);

        $order = $service->place($customer, [['product' => $product, 'quantity' => 10]], self::ADDRESS);
        $this->assertNull($order->items->first()->product_batch_id);

        $confirmed = $service->confirm($order);

        $lines = $confirmed->items->sortBy('batch_code')->values();
        $this->assertSame(['LOT-A', 'LOT-B'], $lines->pluck('batch_code')->all());
        $this->assertSame([3, 7], $lines->pluck('quantity')->all());
        $this->assertSame(400000, (int) $lines->sum('line_total'));
        $this->assertSame(400000, $confirmed->subtotal);
    }

    public function test_confirmation_without_enough_stock_flags_the_order_for_sales_and_takes_nothing(): void
    {
        $customer = $this->user();
        $product = $this->stockedProduct();
        $service = app(OrderService::class);

        $order = $service->place($customer, [['product' => $product, 'quantity' => 8]], self::ADDRESS);
        // Another order takes most of the stock first.
        $other = $service->place($this->user(), [['product' => $product, 'quantity' => 5]], self::ADDRESS);
        $service->confirm($other);

        $this->actingAs($this->user('sales'), 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $order->refresh();
        $this->assertSame(Order::STATUS_STOCK_ISSUE, $order->status);
        $this->assertNotNull($order->issue_note);
        $this->assertSame(5, (int) $product->batches()->sum('quantity_on_hand'));

        $this->getJson('/api/admin/alerts')->assertJsonCount(1, 'stock_issue_orders');

        // Sales can still cancel it.
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    public function test_checkout_uses_prices_from_the_database_not_from_the_browser(): void
    {
        $customer = $this->user();
        $product = $this->stockedProduct();

        $this->actingAs($customer, 'sanctum')->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $this->postJson('/api/checkout', self::ADDRESS + [
            'payment_method' => 'cod',
            // Fields a tampered browser might send: they are ignored.
            'price' => 1,
            'subtotal' => 1,
            'total' => 1,
        ])->assertCreated()->assertJsonPath('order.subtotal', 80000);
    }

    // ----- Complaints -----

    public function test_complaint_within_24_hours_with_a_photo_is_accepted_and_sales_settle_it(): void
    {
        $customer = $this->user();
        $order = $this->deliveredOrder($customer, 3);

        $id = $this->actingAs($customer, 'sanctum')
            ->post('/api/complaints', [
                'order_code' => $order->code,
                'reason' => 'damaged',
                'content' => 'Cà chua bị dập 1/3 túi.',
                'image' => $this->photo(),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'open')
            ->json('id');

        $this->assertCount(1, Storage::disk('public')->allFiles('complaints'));

        $this->actingAs($this->user('sales'), 'sanctum')
            ->patchJson("/api/admin/complaints/{$id}", ['status' => 'resolved'])
            ->assertStatus(422); // a resolution (refund or redelivery) is required

        $this->patchJson("/api/admin/complaints/{$id}", ['status' => 'resolved', 'resolution' => 'redelivery', 'admin_reply' => 'Giao bù 1 kg chiều nay.'])
            ->assertOk()
            ->assertJsonPath('resolution', 'redelivery');
    }

    public function test_complaint_after_24_hours_is_refused_automatically(): void
    {
        $customer = $this->user();
        $order = $this->deliveredOrder($customer, 25);

        $this->actingAs($customer, 'sanctum')
            ->post('/api/complaints', [
                'order_code' => $order->code,
                'reason' => 'spoiled',
                'content' => 'Rau héo.',
                'image' => $this->photo(),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_complaint_needs_a_jpg_or_png_photo_under_5mb(): void
    {
        $customer = $this->user();
        $order = $this->deliveredOrder($customer, 1);
        $base = ['order_code' => $order->code, 'reason' => 'damaged', 'content' => 'Hỏng'];

        $this->actingAs($customer, 'sanctum');

        $this->post('/api/complaints', $base, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('image');

        $this->post('/api/complaints', $base + ['image' => UploadedFile::fake()->create('bien-ban.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('image');

        $this->post('/api/complaints', $base + ['image' => UploadedFile::fake()->create('lon.png', 6000, 'image/png')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('image');
    }

    // ----- Shipping by address -----

    public function test_shipping_fee_uses_the_distance_from_the_farm_to_the_geocoded_address(): void
    {
        $this->geocode = [['lat' => '11.9404', 'lon' => '108.4583']];

        $service = app(ShippingService::class);
        $expectedKm = (int) ceil($service->haversineKm(
            config('farm.latitude'), config('farm.longitude'), 11.9404, 108.4583
        ) * config('services.maps.road_factor'));

        $this->getJson('/api/shipping/estimate?subtotal=100000&address='.urlencode('Hồ Xuân Hương, Đà Lạt'))
            ->assertOk()
            ->assertJsonPath('source', 'osm')
            ->assertJsonPath('distance_km', $expectedKm)
            ->assertJsonPath('shipping_fee', $service->fee(100000, $expectedKm));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'nominatim') && $request->hasHeader('User-Agent'));
    }

    public function test_unknown_address_falls_back_to_the_typed_distance(): void
    {
        $this->getJson('/api/shipping/estimate?subtotal=100000&distance_km=4&address='.urlencode('khong ton tai xyz'))
            ->assertOk()
            ->assertJsonPath('source', 'manual')
            ->assertJsonPath('distance_km', 4)
            ->assertJsonPath('shipping_fee', 20000);
    }

    // ----- Settings, diary, reviews, farm -----

    public function test_admin_changes_how_many_days_before_expiry_sales_are_alerted(): void
    {
        $this->stockedProduct(); // lot A expires in 2 days, lot B in 9

        $this->actingAs($this->user('sales'), 'sanctum')
            ->getJson('/api/admin/alerts')->assertJsonCount(1, 'expiring_batches');

        $this->actingAs($this->user('admin'), 'sanctum')
            ->putJson('/api/admin/settings', ['expiry_alert_days' => 10])
            ->assertOk()
            ->assertJsonPath('expiry_alert_days', 10);

        $this->getJson('/api/admin/alerts')->assertJsonCount(2, 'expiring_batches');
    }

    public function test_staff_diary_entry_for_a_batch_appears_on_its_qr_trace_page(): void
    {
        $plot = FarmPlot::create(['code' => 'KV-01', 'name' => 'Nhà kính 1', 'certification' => 'VietGAP']);
        $product = Product::factory()->create();
        $batch = ProductBatch::factory()->for($product)->expiringIn(5, 10)->create(['farm_plot_id' => $plot->id]);

        $this->actingAs($this->user('staff'), 'sanctum')
            ->post('/api/admin/farm-logs', [
                'product_batch_id' => $batch->id,
                'type' => 'fertilizing',
                'description' => 'Bón phân hữu cơ vi sinh.',
                'logged_on' => now()->subDays(10)->toDateString(),
                'media' => $this->photo('bon-phan.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('farm_plot.id', $plot->id)
            ->assertJsonPath('product.id', $product->id);

        $this->getJson('/api/trace/'.$batch->trace_code)
            ->assertOk()
            ->assertJsonCount(1, 'logs')
            ->assertJsonPath('logs.0.type', 'fertilizing');
    }

    public function test_customers_review_products_of_delivered_orders_only(): void
    {
        $customer = $this->user();
        $order = $this->deliveredOrder($customer, 2);
        $productId = $order->items->first()->product_id;

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/orders/{$order->code}/reviews", ['product_id' => $productId, 'rating' => 5, 'comment' => 'Rất tươi'])
            ->assertCreated();

        $pending = app(OrderService::class)->place($customer, [['product' => $this->stockedProduct(), 'quantity' => 1]], self::ADDRESS);
        $this->postJson("/api/orders/{$pending->code}/reviews", ['product_id' => $pending->items->first()->product_id, 'rating' => 4])
            ->assertStatus(422);

        $this->getJson('/api/products/'.Product::find($productId)->slug)
            ->assertOk()
            ->assertJsonPath('rating.count', 1)
            ->assertJsonPath('reviews.0.comment', 'Rất tươi');
    }

    public function test_public_farm_profile(): void
    {
        $this->getJson('/api/farm')
            ->assertOk()
            ->assertJsonPath('name', config('farm.name'))
            ->assertJsonStructure(['name', 'slogan', 'address', 'certifications', 'plots', 'stats']);
    }
}
