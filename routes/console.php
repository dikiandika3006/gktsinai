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
Artisan::command('sij:backup', function () {
    $db = config('database.connections.mysql');
    $dir = storage_path('app/backups');
    File::ensureDirectoryExists($dir);
    $file = $dir.'/sij-'.now()->format('Ymd-His').'.sql.gz';
    $dump = env('MYSQLDUMP_PATH', 'mysqldump');
    $cmd = sprintf('%s --single-transaction --routines --host=%s --port=%s --user=%s %s %s | gzip > %s',
        escapeshellarg($dump), escapeshellarg($db['host']), escapeshellarg((string) $db['port']), escapeshellarg($db['username']),
        $db['password'] ? '--password='.escapeshellarg($db['password']) : '', escapeshellarg($db['database']), escapeshellarg($file));
    exec($cmd, $out, $code);
    if ($code !== 0 || ! file_exists($file) || filesize($file) < 100) {
        $this->error('Backup gagal.');

        return 1;
    }
    if ($disk = env('BACKUP_DISK')) {
        Storage::disk($disk)->put('sij-backups/'.basename($file), fopen($file, 'r'));
    }
    foreach (File::files($dir) as $f) {
        if ($f->getMTime() < now()->subDays(30)->getTimestamp()) {
            File::delete($f->getPathname());
        }
    }
    $this->info('Backup tersimpan: '.$file);
})->purpose('Backup database harian (simpan 30 hari)');

Schedule::command('sij:fu-reminder')->hourly();
Schedule::command('sij:geocode')->dailyAt('01:00');
Schedule::command('sij:refresh-attention')->dailyAt('02:00');
Schedule::command('sij:backup')->dailyAt('03:00');
