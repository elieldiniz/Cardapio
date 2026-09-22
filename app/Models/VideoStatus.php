<?php

namespace App\Models;

use App\Models\Concerns\IsLookupTable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug'])]
class VideoStatus extends Model
{
    use IsLookupTable;
}
