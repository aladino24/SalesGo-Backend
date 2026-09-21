<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('program_type', 40)->default('order_discount')->after('type');
            $table->string('status', 30)->default('Active')->after('is_active');
            $table->json('eligibility_rules')->nullable()->after('minimum_order_amount');
            $table->json('benefit_rules')->nullable()->after('eligibility_rules');
            $table->json('stacking_rules')->nullable()->after('benefit_rules');
            $table->unsignedInteger('quota_total')->nullable()->after('stacking_rules');
            $table->unsignedInteger('quota_per_outlet')->nullable()->after('quota_total');
            $table->unsignedInteger('used_quota')->default(0)->after('quota_per_outlet');
            $table->decimal('budget_amount', 18, 2)->nullable()->after('used_quota');
            $table->decimal('used_budget', 18, 2)->default(0)->after('budget_amount');
            $table->index(['branch_id', 'status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status', 'starts_at', 'ends_at']);
            $table->dropColumn(['program_type', 'status', 'eligibility_rules', 'benefit_rules', 'stacking_rules', 'quota_total', 'quota_per_outlet', 'used_quota', 'budget_amount', 'used_budget']);
        });
    }
};
