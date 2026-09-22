<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

abstract class LookupSeeder extends Seeder
{
    /**
     * The lookup model class to seed.
     *
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * The documented rows to seed, keyed by slug and valued by display name.
     *
     * @return array<string, string>
     */
    abstract protected function rows(): array;

    /**
     * Seed the lookup table idempotently.
     */
    public function run(): void
    {
        $model = $this->model();

        foreach ($this->rows() as $slug => $name) {
            $model::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
