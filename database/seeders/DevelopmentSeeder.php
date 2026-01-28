<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crea le aziende (solo per sviluppo/testing)
        $this->call(CompanyDevelopmentSeeder::class);
        
        // 2. Crea gli utenti per le aziende (solo per sviluppo/testing)
        $this->call(UserDevelopmentSeeder::class);
        
        // 3. Crea i fornitori
        $this->seedSuppliers();
        
        // 4. Crea i brand (solo per sviluppo/testing)
        $this->seedBrands();
        
        // 5. Crea gli stati dei ticket
        $this->call(TicketStageSeeder::class);
        
        // 6. Crea le cause dei ticket
        $this->call(TicketCauseSeeder::class);
        
        // 7. Crea le cause dei valori di fatturabilità
        $this->call(BillableValueCauseSeeder::class);
        
        // 8. Crea gli stati dei contratti
        $this->call(ContractStageSeeder::class);
        
        // 9. Crea gli stati di pagamento delle fatture
        $this->call(InvoicePaymentStageSeeder::class);
        
        // 10. Crea i tipi di hardware
        $this->call(HardwareTypeSeeder::class);
        
        // 11. Crea i tipi di software
        $this->call(SoftwareTypeSeeder::class);

        // 12. Crea i gruppi (solo per sviluppo/testing)
        $this->call(GroupDevelopmentSeeder::class);
        
        // 13. Crea le categorie dei tipi di ticket (solo per sviluppo/testing)
        $this->call(TicketTypeCategoryDevelopmentSeeder::class);

        // 14. Crea i tipi di ticket (solo per sviluppo/testing)
        $this->call(TicketTypeDevelopmentSeeder::class);

        // 15. Crea gli hardware di sviluppo (solo per sviluppo/testing)
        $this->call(HardwareDevelopmentSeeder::class);
    }

    /**
     * Crea i fornitori.
     */
    protected function seedSuppliers(): void
    {
        $suppliers = [
            ['name' => 'iForTest'],
            ['name' => 'CleTest'],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::firstOrCreate(
                ['name' => $supplierData['name']]
            );
        }
    }

    /**
     * Crea i brand.
     */
    protected function seedBrands(): void
    {
        $iForTestSupplier = Supplier::where('name', 'iForTest')->first();
        $cleTestSupplier = Supplier::where('name', 'CleTest')->first();

        $brands = [
            [
                'name' => 'iForBrand1',
                'description' => 'Brand 1 di iForTest',
                'supplier_id' => $iForTestSupplier->id,
            ],
            [
                'name' => 'iForBrand2',
                'description' => 'Brand 2 di iForTest',
                'supplier_id' => $iForTestSupplier->id,
            ],
            [
                'name' => 'CleBrand1',
                'description' => 'Brand 1 di CleTest',
                'supplier_id' => $cleTestSupplier->id,
            ],
            [
                'name' => 'CleBrand2',
                'description' => 'Brand 2 di CleTest',
                'supplier_id' => $cleTestSupplier->id,
            ],
        ];

        foreach ($brands as $brandData) {
            Brand::firstOrCreate(
                ['name' => $brandData['name']],
                $brandData
            );
        }
    }
}
