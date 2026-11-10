<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Region extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['area_geojson' => 'array', 'is_active' => 'boolean'];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(Member::class, Family::class);
    }

    /** Apakah titik (lat, lng) berada di dalam poligon area wilayah ini. */
    public function contains(float $lat, float $lng): bool
    {
        $geo = $this->area_geojson;
        if (! $geo) {
            return false;
        }
        $geom = $geo['type'] === 'Feature' ? $geo['geometry'] : ($geo['type'] === 'FeatureCollection' ? ($geo['features'][0]['geometry'] ?? null) : $geo);
        if (! $geom) {
            return false;
        }
        $polygons = match ($geom['type']) {
            'Polygon' => [$geom['coordinates']],
            'MultiPolygon' => $geom['coordinates'],
            default => [],
        };
        foreach ($polygons as $rings) {
            if (self::pointInRing($lng, $lat, $rings[0])) {
                return true;
            }
        }

        return false;
    }

    private static function pointInRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    public static function suggestFor(?float $lat, ?float $lng): ?self
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return static::query()->whereNotNull('area_geojson')->get()
            ->first(fn (Region $r) => $r->contains($lat, $lng));
    }
}
