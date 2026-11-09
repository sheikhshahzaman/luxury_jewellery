<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slug
{
    /**
     * A slug unique within $model's table. Names with no letters or digits
     * (e.g. "★★★") fall back to "item-xxxxxx" so routes never get an empty key.
     *
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item-'.Str::lower(Str::random(6));
        $slug = $base;
        $n = 2;

        while ($model::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
