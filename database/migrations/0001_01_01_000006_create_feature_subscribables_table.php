<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('subscriptionify.tables.feature_subscribable', 'feature_subscribable');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('feature_id');
                $table->morphs('subscribable');
                $table->string('value')->default('0');
                $table->decimal('unit_price', 16, 8)->default(0);
                $table->unsignedInteger('reset_period')->nullable();
                $table->string('reset_interval')->nullable();
                $table->timestamps();

                $table->unique(['feature_id', 'subscribable_type', 'subscribable_id'], 'feature_subscribable_unique');
                $table->index('feature_id', 'feature_subscribable_feature_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptionify.tables.feature_subscribable', 'feature_subscribable'));
    }
};
