<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('subscriptionify.tables.feature_plan', 'feature_plan');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table): void {
                $table->foreignId('plan_id');
                $table->foreignId('feature_id');
                $table->string('value')->default('0');
                $table->decimal('unit_price', 16, 8)->default(0);
                $table->unsignedInteger('reset_period')->nullable();
                $table->string('reset_interval')->nullable();
                $table->primary(['plan_id', 'feature_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptionify.tables.feature_plan', 'feature_plan'));
    }
};
