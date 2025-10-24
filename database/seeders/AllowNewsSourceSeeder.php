<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AllowNewsSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        $companies = \App\Models\Company::all();

        foreach ($companies as $company) {
            $company->newsSources()->sync(
                \App\Models\NewsSource::pluck('id')->all()
            );
        }

    }   
}
