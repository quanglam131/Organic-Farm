<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FarmLog;
use App\Models\FarmPlot;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanItem;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data for a single farm: accounts, plots, products, batches with different
 * expiry dates (for FEFO and expiry alerts), subscription plans and diary entries.
 *
 *   admin@farm.local / password   (quản trị)
 *   staff@farm.local / password   (nhân viên nông trại: lô hàng, nhật ký, QR, xuất hủy)
 *   sales@farm.local / password   (nhân viên bán hàng / CSKH: đơn hàng, khiếu nại)
 *   khach@farm.local / password   (khách hàng)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@farm.local'], [
            'name' => 'Quản trị viên',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'phone' => '0900000001',
        ]);

        $staff = User::updateOrCreate(['email' => 'staff@farm.local'], [
            'name' => 'Anh Ba (nhân viên vườn)',
            'password' => 'password',
            'role' => User::ROLE_STAFF,
            'phone' => '0900000003',
        ]);

        User::updateOrCreate(['email' => 'sales@farm.local'], [
            'name' => 'Chị Lan (bán hàng)',
            'password' => 'password',
            'role' => User::ROLE_SALES,
            'phone' => '0900000004',
        ]);

        User::updateOrCreate(['email' => 'khach@farm.local'], [
            'name' => 'Khách hàng demo',
            'password' => 'password',
            'role' => User::ROLE_CUSTOMER,
            'phone' => '0900000002',
            'address' => 'Quận 1, TP. Hồ Chí Minh',
            'points_balance' => 0,
        ]);

        $vegetables = Category::firstOrCreate(['slug' => 'rau-cu'], ['name' => 'Rau củ']);
        $fruits = Category::firstOrCreate(['slug' => 'trai-cay'], ['name' => 'Trái cây']);
        $fresh = Category::firstOrCreate(['slug' => 'thuc-pham-tuoi'], ['name' => 'Thực phẩm tươi']);

        // The farm's own growing areas (one farm, several plots).
        $plotVeg = FarmPlot::updateOrCreate(['code' => 'LD-01'], [
            'name' => 'Khu A - Nhà kính rau ôn đới',
            'location' => 'Xuân Thọ, Đà Lạt',
            'area_m2' => 2500,
            'certification' => 'VietGAP',
            'description' => 'Trồng rau trong nhà màng, tưới nhỏ giọt, không dùng thuốc trừ sâu hóa học trong 21 ngày trước thu hoạch.',
        ]);

        $plotFruit = FarmPlot::updateOrCreate(['code' => 'LD-02'], [
            'name' => 'Khu B - Vườn cây ăn trái',
            'location' => 'Xuân Thọ, Đà Lạt',
            'area_m2' => 5000,
            'certification' => 'Hữu cơ',
            'description' => 'Vườn trái cây canh tác hữu cơ, ủ phân từ phụ phẩm trong trang trại.',
        ]);

        // name, category, price (VND), unit, plot, [quantity, days until expiry] per batch
        $catalog = [
            ['Xà lách lô lô', $vegetables, 25000, 'kg', $plotVeg, [[20, 2], [30, 9]]],
            ['Cà chua bi', $vegetables, 45000, 'kg', $plotVeg, [[15, 1], [40, 12]]],
            ['Cải ngọt', $vegetables, 22000, 'kg', $plotVeg, [[25, 5], [10, -1]]],
            ['Dâu tây Đà Lạt', $fruits, 120000, 'hộp 500g', $plotFruit, [[30, 3]]],
            ['Bơ sáp', $fruits, 60000, 'kg', $plotFruit, [[40, 14]]],
            ['Trứng gà ta', $fresh, 55000, 'vỉ 10 quả', $plotFruit, [[50, 20]]],
        ];

        $inventory = app(InventoryService::class);
        $products = [];
        $firstBatch = [];

        foreach ($catalog as [$name, $category, $price, $unit, $plot, $batches]) {
            $product = Product::firstOrCreate(['slug' => Str::slug($name)], [
                'category_id' => $category->id,
                'name' => $name,
                'unit' => $unit,
                'price' => $price,
                'description' => "{$name} tươi, thu hoạch theo đơn, giao trong ngày.",
                'is_active' => true,
            ]);
            $products[$name] = $product;

            foreach ($batches as [$quantity, $daysToExpiry]) {
                $batch = $inventory->receive([
                    'product_id' => $product->id,
                    'farm_plot_id' => $plot->id,
                    'quantity_received' => $quantity,
                    'harvested_on' => now()->subDays(2)->toDateString(),
                    'expires_on' => now()->addDays($daysToExpiry)->toDateString(),
                ], $staff->id);
                $firstBatch[$name] ??= $batch;
            }
        }

        // Marks the batch that expired yesterday as expired, as the nightly job would.
        $inventory->expireDueBatches();

        FarmLog::firstOrCreate(['farm_plot_id' => $plotVeg->id, 'type' => 'planting', 'logged_on' => now()->subDays(30)->toDateString()], [
            'description' => 'Gieo hạt xà lách và cà chua bi trên giàn thủy canh, mật độ 25 cây/m².',
            'created_by' => $staff->id,
        ]);
        FarmLog::firstOrCreate(['farm_plot_id' => $plotVeg->id, 'type' => 'fertilizing', 'logged_on' => now()->subDays(14)->toDateString()], [
            'description' => 'Bón phân hữu cơ ủ từ phân trùn quế, tưới dinh dưỡng EC 1.8.',
            'created_by' => $staff->id,
        ]);
        FarmLog::firstOrCreate(['farm_plot_id' => $plotVeg->id, 'type' => 'pest_control', 'logged_on' => now()->subDays(7)->toDateString()], [
            'description' => 'Phòng bệnh bằng chế phẩm sinh học, bẫy dính vàng theo dõi rệp.',
            'created_by' => $staff->id,
            'product_id' => $products['Cà chua bi']->id,
        ]);
        FarmLog::firstOrCreate(['farm_plot_id' => $plotVeg->id, 'type' => 'harvesting', 'logged_on' => now()->subDays(2)->toDateString()], [
            'description' => 'Thu hoạch buổi sáng khi nhiệt độ thấp, làm mát ngay sau khi hái.',
            'created_by' => $staff->id,
            'product_id' => $products['Xà lách lô lô']->id,
            'product_batch_id' => $firstBatch['Xà lách lô lô']->id,
        ]);

        $vegPlan = SubscriptionPlan::firstOrCreate(['name' => 'Gói rau sạch hàng tuần'], [
            'description' => 'Xà lách, cà chua và cải ngọt cho gia đình 3-4 người.',
            'discount_percent' => 10,
            'is_active' => true,
        ]);
        foreach (['Xà lách lô lô' => 1, 'Cà chua bi' => 1, 'Cải ngọt' => 1] as $productName => $qty) {
            SubscriptionPlanItem::firstOrCreate(
                ['subscription_plan_id' => $vegPlan->id, 'product_id' => $products[$productName]->id],
                ['quantity' => $qty],
            );
        }

        $fruitPlan = SubscriptionPlan::firstOrCreate(['name' => 'Gói trái cây tươi'], [
            'description' => 'Dâu tây và bơ sáp cho bữa ăn lành mạnh mỗi tuần.',
            'discount_percent' => 5,
            'is_active' => true,
        ]);
        foreach (['Dâu tây Đà Lạt' => 1, 'Bơ sáp' => 2] as $productName => $qty) {
            SubscriptionPlanItem::firstOrCreate(
                ['subscription_plan_id' => $fruitPlan->id, 'product_id' => $products[$productName]->id],
                ['quantity' => $qty],
            );
        }
    }
}
