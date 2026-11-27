<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    // 1x1 transparent PNG, a real image so the "image" validation rule passes.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        // Force local storage even if Cloudinary credentials exist in .env.
        config(['cloudinary.cloud_name' => null, 'cloudinary.api_key' => null, 'cloudinary.api_secret' => null]);
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => uniqid().'@test.local',
            'password' => 'password-123',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Cà chua',
            'slug' => 'ca-chua-'.uniqid(),
            'unit' => 'kg',
            'price' => 45000,
            'is_active' => true,
        ]);
    }

    private function photo(Product $product, string $url, int $order, bool $primary): ProductImage
    {
        return ProductImage::create([
            'product_id' => $product->id,
            'url' => $url,
            'sort_order' => $order,
            'is_primary' => $primary,
        ]);
    }

    public function test_first_upload_becomes_primary_and_is_mirrored_on_the_product(): void
    {
        $product = $this->product();
        $file = UploadedFile::fake()->createWithContent('ca-chua.png', base64_decode(self::PNG));

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/images", ['image' => $file, 'alt' => 'Cà chua chín'])
            ->assertCreated()
            ->assertJsonPath('is_primary', true)
            ->assertJsonPath('alt', 'Cà chua chín');

        $this->assertNotNull($product->fresh()->image_url);
        $this->assertCount(1, Storage::disk('public')->allFiles('products'));
    }

    public function test_second_upload_is_added_without_changing_the_primary(): void
    {
        $product = $this->product();
        $first = $this->photo($product, 'https://example.test/1.jpg', 0, true);
        $product->update(['image_url' => $first->url]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/images", ['url' => 'https://example.test/2.jpg'])
            ->assertCreated()
            ->assertJsonPath('is_primary', false)
            ->assertJsonPath('sort_order', 1);

        $this->assertSame($first->url, $product->fresh()->image_url);
        $this->assertCount(2, $product->fresh()->images);
    }

    public function test_switching_primary_updates_the_product_and_primary_cannot_be_unset_directly(): void
    {
        $product = $this->product();
        $first = $this->photo($product, 'https://example.test/1.jpg', 0, true);
        $second = $this->photo($product, 'https://example.test/2.jpg', 1, false);
        $product->update(['image_url' => $first->url]);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/product-images/{$second->id}", ['is_primary' => true])
            ->assertOk();

        $this->assertSame($second->url, $product->fresh()->image_url);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);

        $this->patchJson("/api/admin/product-images/{$second->id}", ['is_primary' => false])
            ->assertStatus(422);
    }

    public function test_making_the_current_primary_photo_primary_again_keeps_it_primary(): void
    {
        $product = $this->product();
        $first = $this->photo($product, 'https://example.test/1.jpg', 0, true);
        $this->photo($product, 'https://example.test/2.jpg', 1, false);
        $product->update(['image_url' => $first->url]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/product-images/{$first->id}", ['is_primary' => true])
            ->assertOk()
            ->assertJsonPath('is_primary', true);

        $this->assertTrue($first->fresh()->is_primary);
        $this->assertSame(1, ProductImage::where('product_id', $product->id)->where('is_primary', true)->count());
        $this->assertSame($first->url, $product->fresh()->image_url);
    }

    public function test_deleting_the_primary_photo_promotes_the_next_one(): void
    {
        $product = $this->product();
        $first = $this->photo($product, 'https://example.test/1.jpg', 0, true);
        $second = $this->photo($product, 'https://example.test/2.jpg', 1, false);
        $product->update(['image_url' => $first->url]);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/product-images/{$first->id}")
            ->assertOk();

        $this->assertSame($second->url, $product->fresh()->image_url);
        $this->assertTrue($second->fresh()->is_primary);

        $this->deleteJson("/api/admin/product-images/{$second->id}")->assertOk();
        $this->assertNull($product->fresh()->image_url);
    }

    public function test_customers_cannot_manage_images_and_the_shop_shows_the_gallery(): void
    {
        $product = $this->product();
        $this->photo($product, 'https://example.test/1.jpg', 0, true);
        $this->photo($product, 'https://example.test/2.jpg', 1, false);

        $customer = User::create([
            'name' => 'Khách',
            'email' => uniqid().'@test.local',
            'password' => 'password-123',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/images", ['url' => 'https://example.test/x.jpg'])
            ->assertForbidden();

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'images')
            ->assertJsonPath('images.0.is_primary', true);
    }
}
