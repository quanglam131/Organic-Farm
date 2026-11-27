<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tuần 3 ERD requirements:
 *  - Batches record wasted quantity (so_luong_hao_hut); expires_on is indexed for FEFO and expiry alerts.
 *  - Order details point to the batch the goods came from, so a complaint can be traced back.
 *  - Farming logs can point to a batch; the person who wrote the log is kept (created_by).
 *  - Orders are indexed by status + created_at so Sales can filter today's orders quickly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->unsignedInteger('quantity_wasted')->default(0)->after('quantity_on_hand');
            $table->index('expires_on');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');
            $table->string('issue_note')->nullable()->after('note');
            $table->index(['status', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('batch_code')->nullable()->after('product_batch_id');
        });

        Schema::table('farm_logs', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('media_url')->nullable()->after('reason');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('reason', 30)->nullable()->after('order_id');
            $table->string('resolution', 20)->nullable()->after('status');
            $table->foreignId('handled_by')->nullable()->after('admin_reply')->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable()->after('handled_by');
        });

        // The old "paid" order status is now "confirmed": payment is tracked in payment_status.
        DB::table('orders')->where('status', 'paid')->update(['status' => 'confirmed']);

        // Existing waste movements become the batch's wasted quantity.
        foreach (DB::table('inventory_movements')->where('type', 'waste')->get() as $movement) {
            DB::table('product_batches')->where('id', $movement->product_batch_id)
                ->increment('quantity_wasted', abs($movement->quantity));
        }
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn(['reason', 'resolution', 'handled_at']);
        });
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->dropColumn('media_url'));
        Schema::table('farm_logs', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_batch_id'));
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_batch_id');
            $table->dropColumn('batch_code');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['confirmed_at', 'issue_note']);
        });
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropIndex(['expires_on']);
            $table->dropColumn('quantity_wasted');
        });
    }
};
