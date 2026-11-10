<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->roles()->exists();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function visits(): BelongsToMany
    {
        return $this->belongsToMany(Visit::class, 'visit_user');
    }

    // ---- Hak akses (bagian 3 SRS) ----

    public function peran(string ...$peran): bool
    {
        return $this->hasAnyRole($peran);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** Boleh melihat seluruh data jemaat (semua wilayah). */
    public function lihatSemuaJemaat(): bool
    {
        return $this->peran('super_admin', 'sekretariat', 'pendeta', 'majelis');
    }

    /** Boleh menambah/mengubah data jemaat. */
    public function kelolaJemaat(): bool
    {
        return $this->peran('super_admin', 'sekretariat', 'koordinator_wilayah');
    }

    /** Boleh melihat HP, alamat, dan koordinat. */
    public function lihatKontak(): bool
    {
        return $this->peran('super_admin', 'sekretariat', 'pendeta', 'majelis', 'koordinator_wilayah', 'tim_visitasi');
    }

    /** Ekspor Excel berisi HP & alamat. */
    public function eksporKontak(): bool
    {
        return $this->peran('super_admin', 'sekretariat', 'pendeta');
    }

    public function bacaCatatanRahasia(): bool
    {
        return $this->peran('pendeta');
    }

    /** Boleh mengisi/mengimport jadwal pelayanan dan menghubungkan nama pelayan. */
    public function kelolaJadwal(): bool
    {
        return $this->peran('super_admin', 'sekretariat');
    }

    public function kelolaKehadiran(): bool
    {
        return $this->peran('super_admin', 'sekretariat', 'koordinator_wilayah', 'koordinator_komisi', 'penyambut', 'petugas_absensi');
    }

    /** Hanya berperan Petugas Absensi: cukup layar absensi, tanpa panel admin. */
    public function hanyaAbsensi(): bool
    {
        $peran = $this->getRoleNames();

        return $peran->count() === 1 && $peran->first() === 'petugas_absensi';
    }

    public function kelolaTamu(): bool
    {
        return $this->peran('super_admin', 'sekretariat');
    }

    public function kelolaVisitasi(): bool
    {
        return $this->peran('super_admin', 'pendeta');
    }

    public function pinManual(): bool
    {
        return $this->peran('super_admin', 'sekretariat');
    }

    /** ID komisi yang dikoordinasi user. */
    public function komisiDikoordinasi(): array
    {
        return Commission::query()
            ->where('coordinator_id', $this->id)
            ->when($this->commission_id, fn ($q) => $q->orWhere('id', $this->commission_id))
            ->pluck('id')->all();
    }

    /** ID KK yang ditugaskan ke user (tim visitasi). */
    public function kkDitugaskan(): array
    {
        return Visit::query()
            ->whereHas('team', fn ($q) => $q->whereKey($this->id))
            ->whereNotNull('family_id')
            ->pluck('family_id')->unique()->values()->all();
    }
}
