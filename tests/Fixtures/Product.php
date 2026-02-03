<?php

namespace Initium\LaravelTranslatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Initium\LaravelTranslatable\Components\Database\Concerns\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public $timestamps = false;
}
