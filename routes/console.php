<?php

use App\Jobs\GeocodeFamily;
use App\Models\Family;
use App\Models\Guest;
use App\Models\User;
use App\Notifications\OverdueFollowupNotification;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

// Import data jemaat dari Excel lama. --fresh menghapus data jemaat/KK/kehadiran/tamu/visitasi (mis. data demo) dulu.
Artisan::command('sij:import-excel {file} {--sheet=0} {--fresh}', function (\App\Services\MemberImporter $importer) {
    $file = $this->argument('file');
    if (! is_file($file)) {
        $this->error("File tidak ditemukan: {$file}");

        return 1;
    }
    if ($this->option('fresh')) {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        foreach (['visit_reports', 'visit_user', 'visits', 'guest_followups', 'attendances', 'guests', 'activity_sessions',
            'commission_members', 'member_events', 'members', 'families', 'audit_logs'] as $t) {
            \Illuminate\Support\Facades\DB::table($t)->truncate();
        }
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        $this->info('Data lama dihapus.');
    }

    $baca = $importer->read($file, (int) $this->option('sheet'));
    $map = $importer->guessMapping($baca['headings']);
    $tidakTerpetakan = array_diff_key($baca['headings'], array_flip(array_filter($map, fn ($i) => $i !== null)));
    $this->line('Kolom tidak dipetakan: '.(implode(', ', $tidakTerpetakan) ?: '-'));

    $hasil = $importer->import($baca['rows'], $map);
    $this->info("Baru: {$hasil['created']}, diperbarui: {$hasil['updated']}, gagal: ".count($hasil['failed']));
    foreach ($hasil['failed'] as $f) {
        $this->warn("Baris {$f['line']} ({$f['name']}): {$f['error']}");
    }
})->purpose('Import data jemaat dari Excel');

// TF-07: pengingat PIC yang FU-nya lewat batas waktu.
Artisan::command('sij:fu-reminder', function () {
    $terlambat = Guest::where('fu_stage', 'baru')->where('fu_due_at', '<', now())->get();
    $perPic = $terlambat->groupBy('pic_id');
    foreach ($perPic as $picId => $guests) {
        $penerima = $picId ? User::whereKey($picId)->get() : User::role('sekretariat')->where('is_active', true)->get();
        foreach ($penerima as $u) {
            $u->notify(new OverdueFollowupNotification($guests));
        }
    }
    $this->info($terlambat->count().' FU terlambat diingatkan.');
})->purpose('Kirim pengingat follow-up tamu yang lewat batas waktu');

// DJ-14: antrekan geocoding untuk KK yang belum diproses.
Artisan::command('sij:geocode {--all : Proses ulang semua kecuali pin manual}', function () {
    $q = Family::whereNotNull('address')->where('geocode_status', '!=', 'manual');
    if (! $this->option('all')) {
        $q->where('geocode_status', 'belum');
    }
    $ids = $q->pluck('id');
    $ids->each(fn ($id) => GeocodeFamily::dispatch($id));
    $this->info($ids->count().' KK masuk antrean geocoding. Pastikan queue worker berjalan.');
})->purpose('Antrekan geocoding alamat KK');

// Hitung ulang daftar perhatian (KH-07) untuk dashboard.
Artisan::command('sij:refresh-attention', function () {
    Cache::put('sij.attention_count', app(AttendanceService::class)->attentionList()->count(), 3600 * 24);
})->purpose('Hitung ulang daftar perhatian');

// Backup database harian, disimpan 30 hari, salinan di disk terpisah (BACKUP_DISK).
// PHP murni (tanpa mysqldump/exec) supaya jalan di shared hosting. File sama dengan menu Tentang → Alt+F7.
Artisan::command('sij:backup', function (\App\Services\DatabaseBackupService $backups) {
    try {
        $name = $backups->exclusive(fn () => $backups->backup());
    } catch (\Throwable $e) {
        $this->error('Backup gagal: '.$e->getMessage());

        return 1;
    }
    $file = $backups->path($name);
    if ($disk = env('BACKUP_DISK')) {
        Storage::disk($disk)->put('sij-backups/'.$name, fopen($file, 'r'));
    }
    foreach ($backups->list() as $b) {
        if ($b['time'] < now()->subDays(30)->getTimestamp()) {
            $backups->delete($b['name']);
        }
    }
    $this->info('Backup tersimpan: '.$file);
})->purpose('Backup database harian (simpan 30 hari)');

Schedule::command('sij:fu-reminder')->hourly();
Schedule::command('sij:geocode')->dailyAt('01:00');
Schedule::command('sij:refresh-attention')->dailyAt('02:00');
Schedule::command('sij:backup')->dailyAt('03:00');
