<?php

use App\Http\Controllers\AbsenController;
use App\Http\Controllers\GuestFormController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\SelfCheckinController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// ---- Halaman publik (tanpa login) ----
// TF-01: form tamu ramah HP
Route::get('/tamu', [GuestFormController::class, 'create'])->name('tamu.create');
Route::post('/tamu', [GuestFormController::class, 'store'])->middleware('throttle:10,1')->name('tamu.store');
Route::get('/tamu/terima-kasih', [GuestFormController::class, 'thanks'])->name('tamu.thanks');

// KH-04: check-in mandiri lewat QR (token berganti tiap sesi, berlaku selama jam ibadah)
Route::get('/c/{token}', [SelfCheckinController::class, 'show'])->name('checkin.self');
Route::get('/c/{token}/cari', [SelfCheckinController::class, 'search'])->middleware('throttle:60,1')->name('checkin.search');
Route::post('/c/{token}', [SelfCheckinController::class, 'store'])->middleware('throttle:30,1')->name('checkin.store');

// ---- Perlu login ----
Route::middleware('auth')->group(function () {
    // KH-03: absensi oleh petugas (PWA)
    Route::get('/absen', [AbsenController::class, 'index'])->name('absen.index');
    Route::post('/absen/buka', [AbsenController::class, 'open'])->name('absen.open');
    Route::get('/absen/{session}', [AbsenController::class, 'show'])->name('absen.session');
    Route::get('/absen/{session}/data', [AbsenController::class, 'data'])->name('absen.data');
    Route::post('/absen/{session}/sync', [AbsenController::class, 'sync'])->name('absen.sync');
    Route::post('/absen/{session}/jumlah', [AbsenController::class, 'count'])->name('absen.count');

    Route::get('/sesi/{session}/qr', [AbsenController::class, 'qr'])->name('sessions.qr');

    // DJ-16, PJ-02, PJ-06
    Route::get('/pdf/jemaat/{member}', [PdfController::class, 'member'])->name('pdf.member');
    Route::get('/pdf/kk/{family}', [PdfController::class, 'family'])->name('pdf.family');
    Route::get('/pdf/rekap', [PdfController::class, 'rekap'])->name('pdf.rekap');
    Route::get('/pdf/bulanan', [PdfController::class, 'monthly'])->name('pdf.monthly');
});
