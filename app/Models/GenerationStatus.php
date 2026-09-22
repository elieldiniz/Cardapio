<?php

namespace App\Models;

use App\Models\Concerns\IsLookupTable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug'])]
class GenerationStatus extends Model
{
    use IsLookupTable;
}
