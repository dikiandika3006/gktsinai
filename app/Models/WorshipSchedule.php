<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\PelayanMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Jadwal ibadah Minggu beserta petugas pelayanannya. */
class WorshipSchedule extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class)->orderBy('urutan');
    }

    /**
     * Ganti seluruh petugas jadwal ini.
     *
     * @param  array<int, array{peran:string, kebaktian:?string, nama:?string}>  $baris
     */
    public function simpanPetugas(array $baris): void
    {
        $matcher = app(PelayanMatcher::class);

        DB::transaction(function () use ($baris, $matcher) {
            $this->assignments()->delete();
            $urutan = 0;
            foreach ($baris as $b) {
                foreach ($matcher->pecah((string) ($b['nama'] ?? '')) as $nama) {
                    $info = $matcher->resolve($nama);
                    if ($info === null) {
                        continue;
                    }
                    $this->assignments()->create([
                        'kebaktian' => ($b['kebaktian'] ?? null) ?: null,
                        'peran' => $b['peran'],
                        'urutan' => $urutan++,
                        'nama' => $nama,
                        'kunci' => $info['kunci'],
                        'is_keluarga' => $info['keluarga'],
                        'member_id' => $info['member_id'],
                    ]);
                }
            }
        });
    }

    /** Petugas untuk form (satu baris per orang). */
    public function petugasUntukForm(): array
    {
        return $this->assignments()->get()
            ->map(fn (ServiceAssignment $a) => ['peran' => $a->peran, 'kebaktian' => $a->kebaktian, 'nama' => $a->nama])
            ->all();
    }
}
