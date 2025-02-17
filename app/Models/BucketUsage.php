<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BucketUsage extends Model
{
    protected $fillable = ['bucket_name', 'total_bytes'];
}
