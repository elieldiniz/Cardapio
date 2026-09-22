<?php

namespace Database\Seeders;

use App\Models\VideoOrigin;

class VideoOriginSeeder extends LookupSeeder
{
    protected function model(): string
    {
        return VideoOrigin::class;
    }

    protected function rows(): array
    {
        return [
            'ia' => 'IA',
            'upload' => 'Upload',
        ];
    }
}
