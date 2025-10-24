<?php

namespace Database\Seeders;

use App\Models\NewsSource;
use Illuminate\Database\Seeder;

class NewsSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //ninja, timenet, BBELL, Fortinet, integys, dpodelcomune

        $defaultNewsSources = [
            [
                'display_name' => 'integys',
                'slug' => 'integys',
                'type' => 'internal_blog',
                'url' => 'https://news.integys.com/',
                'description' => 'Sito di news ufficiali di integys',
            ],
            [
                'display_name' => 'Dpo del Comune',
                'slug' => 'dpo-del-comune',
                'type' => 'internal_blog',
                'url' => 'https://www.dpodelcomune.com/',
                'description' => 'DPO del Comune',
            ],
            [
                'display_name' => 'NinjaOne',
                'slug' => 'ninjaone-blog',
                'type' => 'vendor_blog',
                'url' => 'https://www.ninjaone.com/it/blog/',
                'description' => 'Blog ufficiale di NinjaOne',
            ],
            [
                'display_name' => 'Timenet',
                'slug' => 'timenet-blog',
                'type' => 'vendor_blog',
                'url' => 'https://www.comunicarefacile.it/',
                'description' => 'Blog ufficiale di Timenet',
            ],
            [
                'display_name' => 'BBELL',
                'slug' => 'bbell-blog',
                'type' => 'vendor_blog',
                'url' => 'https://www.bbbell.it/blog/category/articoli/',
                'description' => 'Blog ufficiale di BBELL',
            ],
            [
                'display_name' => 'Fortinet',
                'slug' => 'fortinet-blog',
                'type' => 'vendor_blog',
                'url' => 'https://www.fortinet.com/blog',
                'description' => 'Blog ufficiale di Fortinet',
            ],
        ];

        foreach ($defaultNewsSources as $sourceData) {
            NewsSource::updateOrCreate(
                ['slug' => $sourceData['slug']],
                $sourceData
            );
        }
    }
}
