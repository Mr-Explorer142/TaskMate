<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Model;

trait ForceFillsAttributes
{
    /**
     * Allow factories to seed trusted attributes that are intentionally not
     * mass-assignable from HTTP requests.
     *
     * @param  array<string, mixed>  $attributes
     * @return Model
     */
    public function newModel(array $attributes = [])
    {
        return parent::newModel()->forceFill($attributes);
    }
}
