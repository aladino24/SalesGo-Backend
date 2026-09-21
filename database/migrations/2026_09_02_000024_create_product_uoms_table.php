<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 60);
            // Stok produk selalu disimpan dalam satuan dasar. Nilai ini
            // menyatakan berapa satuan dasar yang terkandung pada 1 UOM.
            $table->unsignedInteger('conversion_to_base')->default(1);
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('minimum_quantity')->default(1);
            $table->unsignedInteger('maximum_quantity')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'code']);
            $table->index(['product_id', 'is_active']);
        });

        DB::table('products')->orderBy('id')->each(function (object $product): void {
            $tokens = array_values(array_filter(array_map(
                fn (string $value) => strtoupper(trim($value)),
                preg_split('/\s*\/\s*/', (string) ($product->uom ?: 'PCS')) ?: [],
            )));
            $tokens = $tokens ?: ['PCS'];
            $caseSize = max(1, (int) ($product->units_per_case ?: 1));
            $last = count($tokens) - 1;
            foreach ($tokens as $index => $code) {
                $conversion = $index === 0 ? 1 : ($index === $last ? $caseSize : max(1, intdiv($caseSize, 2)));
                DB::table('product_uoms')->insert([
                    'product_id' => $product->id,
                    'code' => $code,
                    'name' => $code,
                    'conversion_to_base' => $conversion,
                    'price' => round((float) $product->price * $conversion, 2),
                    'minimum_quantity' => 1,
                    'maximum_quantity' => null,
                    'is_default' => $index === 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_uoms');
    }
};
