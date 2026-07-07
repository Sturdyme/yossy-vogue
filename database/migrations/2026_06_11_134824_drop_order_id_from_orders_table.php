<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'order_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS orders_order_id_unique');
            } catch (\Throwable $e) {
                // Ignore if the index is not present.
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('orders', 'order_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('order_id')->nullable();
            });
        }
    }
};
