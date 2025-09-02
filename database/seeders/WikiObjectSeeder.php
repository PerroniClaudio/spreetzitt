<?php

namespace Database\Seeders;

use App\Models\WikiObject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WikiObjectSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        //

        $path = "/";

        for ($i = 0; $i < 10; $i++) {

            WikiObject::factory()
                ->count(10)
                ->create([
                    'path' => $path,
                ]);

            $randomWord = fake()->word;

            $path .= $randomWord . "/";
        }
    }
}
