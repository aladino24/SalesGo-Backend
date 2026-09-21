<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\SalesDivision;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('code', '001')->firstOrFail();
        $catalog = [
            '01' => ['FOOD', 'Makanan', [
                'Indomie|Mi Goreng Original|85 g|PCS / DUS', 'Indomie|Mi Goreng Rendang|91 g|PCS / DUS', 'Indomie|Mi Goreng Aceh|90 g|PCS / DUS', 'Indomie|Soto Mie|70 g|PCS / DUS', 'Indomie|Ayam Bawang|69 g|PCS / DUS',
                'Mie Sedaap|Mi Goreng|90 g|PCS / DUS', 'Mie Sedaap|Soto|75 g|PCS / DUS', 'Mie Sedaap|Korean Spicy Chicken|87 g|PCS / DUS', 'Roma|Malkist Crackers|135 g|PCS / DUS', 'Roma|Kelapa|300 g|PCS / DUS',
                'Tango|Wafer Chocolate|110 g|PCS / DUS', 'Tango|Wafer Vanilla Delight|110 g|PCS / DUS', 'Chitato|Sapi Panggang|68 g|PCS / DUS', 'Qtela|Singkong Original|60 g|PCS / DUS', 'ABC|Saus Sambal Extra Pedas|335 ml|BTL / DUS',
            ]],
            '02' => ['BEVERAGE', 'Minuman', [
                'AQUA|Air Mineral|600 ml|BTL / DUS', 'AQUA|Air Mineral|1.5 L|BTL / DUS', 'Le Minerale|Air Mineral|600 ml|BTL / DUS', 'Le Minerale|Air Mineral|1.5 L|BTL / DUS', 'Teh Botol Sosro|Original|350 ml|BTL / DUS',
                'Frestea|Jasmine Tea|350 ml|BTL / DUS', 'Pucuk Harum|Teh Melati|350 ml|BTL / DUS', 'Good Day|Cappuccino|250 ml|BTL / DUS', 'Kapal Api|Special Mix|25 g|SACHET / RENTENG / DUS', 'Torabika|Cappuccino|25 g|SACHET / RENTENG / DUS',
                'Nescafe|Classic|2 g|SACHET / BOX / DUS', 'Ultra Milk|Chocolate|200 ml|PCS / DUS', 'Ultra Milk|Strawberry|200 ml|PCS / DUS', 'Ultra Milk|Full Cream|250 ml|PCS / DUS', 'Hydro Coco|Original Coconut Water|250 ml|PCS / DUS',
            ]],
            '03' => ['PERSONAL_CARE', 'Perawatan Pribadi', [
                'Clear|Anti Dandruff Complete Soft Care Shampoo|160 ml|BTL / DUS', 'Sunsilk|Black Shine Shampoo|160 ml|BTL / DUS', 'Pantene|Hair Fall Control Shampoo|160 ml|BTL / DUS', 'Lifebuoy|Total 10 Bar Soap|80 g|PCS / PACK / DUS', 'Lux|Soft Touch Bar|80 g|PCS / PACK / DUS',
                'Dove|Beauty Moisture Bar|90 g|PCS / PACK / DUS', 'Pepsodent|Pencegah Gigi Berlubang|120 g|PCS / DUS', 'Closeup|Menthol Fresh Toothpaste|160 g|PCS / DUS', 'Biore|Facial Foam Bright & Oil Clear|100 g|TUBE / DUS', 'Biore|Facial Foam Acne Care|100 g|TUBE / DUS',
                'Nivea|Men Extra Bright Facial Foam|100 g|TUBE / DUS', 'Garnier|Bright Complete Vitamin C Face Wash|100 ml|TUBE / DUS', 'Vaseline|Healthy Bright UV Extra Brightening Lotion|200 ml|BTL / DUS', 'Rexona|Men Ice Cool Deodorant Roll On|45 ml|BTL / DUS', 'Citra|Natural Glow UV Body Lotion|180 ml|BTL / DUS',
            ]],
            '04' => ['HOME_CARE', 'Perawatan Rumah', [
                'Rinso|Anti Noda Detergent Powder|800 g|PCS / DUS', 'Rinso|Molto Rose Fresh Liquid Detergent|700 ml|POUCH / DUS', 'Attack|Easy Detergent Powder|800 g|PCS / DUS', 'Attack|Plus Softener Detergent|800 g|PCS / DUS', 'So Klin|Softergent Detergent Powder|770 g|PCS / DUS',
                'Daia|Detergent Powder|800 g|PCS / DUS', 'Sunlight|Jeruk Nipis Dishwashing Liquid|650 ml|POUCH / DUS', 'Mama Lemon|Jeruk Nipis Dishwashing Liquid|680 ml|POUCH / DUS', 'Wipol|Karbol Cemara|780 ml|BTL / DUS', 'Super Pell|Pembersih Lantai Lemon|770 ml|BTL / DUS',
                'Molto|All-in-1 Blue Fabric Conditioner|820 ml|POUCH / DUS', 'Downy|Sunrise Fresh Fabric Conditioner|680 ml|POUCH / DUS', 'Baygon|Aerosol Lavender|600 ml|CAN / DUS', 'Hit|Aerosol Green Tea|600 ml|CAN / DUS', 'Vixal|Pembersih Kamar Mandi|500 ml|BTL / DUS',
            ]],
            '05' => ['BABY_CARE', 'Perawatan Bayi', [
                'MamyPoko|Pants Standard M|28 pcs|PACK / DUS', 'MamyPoko|Pants Standard L|24 pcs|PACK / DUS', 'MamyPoko|Pants Standard XL|20 pcs|PACK / DUS', 'Sweety|Gold Pants M|30 pcs|PACK / DUS', 'Sweety|Gold Pants L|26 pcs|PACK / DUS',
                'Sweety|Gold Pants XL|22 pcs|PACK / DUS', 'Merries|Pants M|28 pcs|PACK / DUS', 'Merries|Pants L|24 pcs|PACK / DUS', 'Merries|Pants XL|20 pcs|PACK / DUS', 'Zwitsal|Baby Shampoo Natural|300 ml|BTL / DUS',
                'Zwitsal|Baby Bath Natural Milk & Honey|300 ml|BTL / DUS', 'My Baby|Baby Powder Soft & Gentle|150 g|BTL / DUS', 'My Baby|Minyak Telon Plus|90 ml|BTL / DUS', 'Cussons Baby|Hair & Body Wash Mild & Gentle|200 ml|BTL / DUS', 'Johnson\'s|Baby Oil|125 ml|BTL / DUS',
            ]],
            '06' => ['DAIRY', 'Susu & Olahan', [
                'Ultra Milk|Full Cream UHT Milk|1 L|PCS / DUS', 'Ultra Milk|Chocolate UHT Milk|200 ml|PCS / DUS', 'Frisian Flag|Purefarm Full Cream UHT|225 ml|PCS / DUS', 'Frisian Flag|Purefarm Chocolate UHT|225 ml|PCS / DUS', 'Indomilk|UHT Chocolate|190 ml|PCS / DUS',
                'Indomilk|UHT Full Cream|950 ml|PCS / DUS', 'Greenfields|Fresh Milk Full Cream|1 L|PCS / DUS', 'Greenfields|Fresh Milk Low Fat|1 L|PCS / DUS', 'Bear Brand|Sterilized Milk|189 ml|CAN / DUS', 'Milo|Activ-Go UHT|180 ml|PCS / DUS',
                'Yakult|Probiotic Drink|65 ml x 5|PACK / DUS', 'Cimory|Yogurt Drink Strawberry|250 ml|BTL / DUS', 'Cimory|Yogurt Drink Blueberry|250 ml|BTL / DUS', 'Kraft|Cheddar Cheese|165 g|PCS / DUS', 'Prochiz|Gold Cheddar Cheese|170 ml|PCS / DUS',
            ]],
            '07' => ['FROZEN', 'Makanan Beku', [
                'Fiesta|Chicken Nugget|500 g|PACK / DUS', 'Fiesta|Chicken Nugget Dino|500 g|PACK / DUS', 'Fiesta|Spicy Wing|500 g|PACK / DUS', 'So Good|Chicken Nugget Original|400 g|PACK / DUS', 'So Good|Chicken Nugget Alphabet|400 g|PACK / DUS',
                'So Good|Chicken Wings|400 g|PACK / DUS', 'Champ|Chicken Nugget|500 g|PACK / DUS', 'Champ|Chicken Sausage|375 g|PACK / DUS', 'Belfoods|Chicken Nugget|500 g|PACK / DUS', 'Belfoods|Chicken Sausage|500 g|PACK / DUS',
                'Kanzler|Crispy Chicken Nugget|450 g|PACK / DUS', 'Kanzler|Chicken Cordon Bleu|300 g|PACK / DUS', 'Kanzler|Singles Original Sausage|65 g|PACK / DUS', 'Bernardi|Bakso Sapi|500 g|PACK / DUS', 'Golden Farm|French Fries Shoestring|1 kg|PACK / DUS',
            ]],
            '08' => ['CONFECTIONERY', 'Camilan & Permen', [
                'SilverQueen|Cashew Milk Chocolate|58 g|PCS / BOX / DUS', 'SilverQueen|Almond Milk Chocolate|58 g|PCS / BOX / DUS', 'Delfi|Dairy Milk Chocolate|50 g|PCS / BOX / DUS', 'Beng-Beng|Chocolate Wafer|20 g|PCS / BOX / DUS', 'Choki Choki|Chocolate Paste|20 g|PCS / BOX / DUS',
                'Kopiko|Coffee Candy|150 g|BAG / DUS', 'Kopiko|Cappuccino Candy|150 g|BAG / DUS', 'Mentos|Mint Roll|37 g|ROLL / BOX / DUS', 'Mentos|Fruit Roll|37 g|ROLL / BOX / DUS', 'Fox\'s|Crystal Clear Candy|90 g|BAG / DUS',
                'Relaxa|Barley Mint Candy|125 g|BAG / DUS', 'Tango|Chocolate Wafer|110 g|PCS / DUS', 'Gery|Chocolatos Wafer Roll|16 g|PCS / BOX / DUS', 'KitKat|4 Finger Chocolate|35 g|PCS / BOX / DUS', 'Toblerone|Milk Chocolate|100 g|PCS / BOX / DUS',
            ]],
        ];
        $images = [
            'Mie Sedaap' => 'products/mie-sedaap.jpg',
            'Indomilk|UHT Chocolate' => 'products/indomilk-chocolate.png',
            'Biore' => 'products/mens-biore-micro-bright-scrub.jpg',
            'Attack' => 'products/detergent-attack-sensor-matic.jpg',
            'Gery|Chocolatos Wafer Roll' => 'products/chocolatos-wafer-stick.jpg',
        ];
        foreach ($catalog as $code => [$divisionName, $category, $items]) {
            $division = SalesDivision::updateOrCreate(['code' => $code], ['name' => $divisionName]);
            foreach ($items as $index => $item) {
                [$brand, $variant, $size, $uom] = explode('|', $item);
                $sku = sprintf('%s-%s-%03d', $branch->code, $code, $index + 1);
                // Gambar hanya disimpan bila memang tersedia untuk produk
                // spesifik. Tidak memakai fallback gambar per divisi.
                $imagePath = $images[$brand.'|'.$variant] ?? $images[$brand] ?? null;
                $product = Product::updateOrCreate(
                    ['branch_id' => $branch->id, 'sku' => $sku],
                    [
                        'sales_division_id' => $division->id,
                        'branch_code' => $branch->code,
                        'name' => $brand.' '.$variant,
                        'brand' => $brand,
                        'variant' => $variant,
                        'size' => $size,
                        'uom' => $uom,
                        'units_per_case' => 12,
                        'barcode' => $branch->code.$code.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
                        'category' => $category,
                        'price' => 4000 + (($index + 1) * 1250),
                        'stock' => 30 + (($index + 1) * 7),
                        'image_path' => $imagePath,
                        'is_active' => true,
                    ],
                );
                $this->syncUoms($product, $uom);
            }
        }
    }

    private function syncUoms(Product $product, string $definition): void
    {
        $codes = array_values(array_filter(array_map('trim', explode('/', $definition))));
        $caseSize = max(1, (int) $product->units_per_case);
        $last = count($codes) - 1;
        foreach ($codes as $index => $code) {
            $conversion = $index === 0 ? 1 : ($index === $last ? $caseSize : max(1, intdiv($caseSize, 2)));
            ProductUom::updateOrCreate(
                ['product_id' => $product->id, 'code' => strtoupper($code)],
                [
                    'name' => strtoupper($code),
                    'conversion_to_base' => $conversion,
                    'price' => (float) $product->price * $conversion,
                    'minimum_quantity' => 1,
                    'maximum_quantity' => null,
                    'is_default' => $index === 0,
                    'is_active' => true,
                ],
            );
        }
    }
}
