<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\WorshipSchedule;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Membaca file Excel jadwal ibadah dengan format:
 * baris "Tanggal" berisi tanggal (tiap tanggal 2 kolom: Ibadah Umum I & II),
 * lalu baris Pembicara, Tema, Ayat Referensi, Introitus, PHB, Liturgis, Singer, Pewarta, LCD,
 * Penyambut & Kolektan, Keterangan. Sel yang di-merge melintasi kedua kolom = berlaku untuk kedua kebaktian.
 */
class JadwalImporter
{
    private const BULAN = [
        'januari' => 'january', 'februari' => 'february', 'pebruari' => 'february', 'maret' => 'march', 'april' => 'april',
        'mei' => 'may', 'juni' => 'june', 'juli' => 'july', 'agustus' => 'august', 'september' => 'september',
        'oktober' => 'october', 'november' => 'november', 'nopember' => 'november', 'desember' => 'december',
    ];

    /** Kata kunci label baris => field jadwal / peran petugas. */
    private const LABEL = [
        'pembicara' => ['peran', 'pembicara'], 'pengkhotbah' => ['peran', 'pembicara'], 'khotbah' => ['peran', 'pembicara'],
        'tema' => ['info', 'tema'], 'ayat' => ['info', 'ayat_referensi'], 'introitus' => ['info', 'introitus'],
        'phb' => ['info', 'phb'], 'keterangan' => ['info', 'keterangan'],
        'liturgis' => ['peran', 'liturgis'], 'singer' => ['peran', 'singer'], 'pemusik' => ['peran', 'pemusik'],
        'musik' => ['peran', 'pemusik'], 'pewarta' => ['peran', 'pewarta'], 'lcd' => ['peran', 'lcd'], 'multimedia' => ['peran', 'lcd'],
        'penyambut' => ['peran', 'penyambut'], 'kolektan' => ['peran', 'penyambut'],
    ];

    /** @return array<string, array{info: array, petugas: array}> jadwal per tanggal (Y-m-d) */
    public function baca(string $path): array
    {
        $hasil = [];
        foreach (IOFactory::load($path)->getWorksheetIterator() as $sheet) {
            $hasil += $this->bacaSheet($sheet);
        }
        ksort($hasil);

        return $hasil;
    }

    /** Simpan ke database (menimpa jadwal pada tanggal yang sama). */
    public function simpan(array $jadwal): array
    {
        $petugas = 0;
        foreach ($jadwal as $tanggal => $j) {
            $s = WorshipSchedule::updateOrCreate(['date' => $tanggal], $j['info']);
            $s->simpanPetugas($j['petugas']);
            $petugas += $s->assignments()->count();
        }
        $tanggal = array_keys($jadwal);
        $belum = $tanggal ? ServiceAssignment::whereNull('member_id')
            ->whereHas('schedule', fn ($query) => $query->whereIn('date', $tanggal))
            ->whereDoesntHave('alias', fn ($query) => $query->whereNotNull('category'))
            ->distinct()->count('kunci') : 0;

        return ['tanggal' => count($jadwal), 'petugas' => $petugas, 'belum_terdaftar' => $belum];
    }

    private function bacaSheet(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, false, false);
        // Sel yang di-merge: isi seluruh area dengan nilai sel kiri-atas, catat yang melintasi kolom.
        $mergeId = [];
        foreach ($sheet->getMergeCells() as $range) {
            [[$c1, $r1], [$c2, $r2]] = Coordinate::rangeBoundaries($range);
            for ($r = $r1; $r <= $r2; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    $rows[$r - 1][$c - 1] = $rows[$r1 - 1][$c1 - 1] ?? null;
                    if ($c2 > $c1) {
                        $mergeId[$r - 1][$c - 1] = $range;
                    }
                }
            }
        }

        $hasil = [];
        $blok = null; // ['labelCol' => int, 'tanggal' => [col => Y-m-d]]
        $labelSebelum = null;
        foreach ($rows as $r => $row) {
            // Baris "Tanggal" memulai blok baru.
            foreach ($row as $c => $v) {
                if (is_string($v) && preg_match('/^\s*tanggal\s*$/i', $v)) {
                    $tgl = [];
                    foreach ($row as $c2 => $v2) {
                        if ($c2 > $c && ($t = $this->tanggal($v2)) && ! in_array($t, $tgl, true)) {
                            $tgl[$c2] = $t;
                        }
                    }
                    // Baris kedua sel "Tanggal" yang di-merge (Ibadah Umum I/II) tidak memulai blok baru.
                    if ($tgl) {
                        $blok = ['labelCol' => $c, 'tanggal' => $tgl];
                        foreach ($tgl as $t) {
                            $hasil[$t] ??= ['info' => [], 'petugas' => []];
                        }
                    }
                    $labelSebelum = null;

                    continue 2;
                }
            }
            if (! $blok || ! $blok['tanggal']) {
                continue;
            }

            $labelTeks = trim((string) ($row[$blok['labelCol']] ?? ''));
            $target = $labelTeks === '' ? $labelSebelum : $this->label($labelTeks);
            if ($labelTeks !== '' && ! $target && $labelSebelum && ($rows[$r - 1][$blok['labelCol']] ?? null) === $row[$blok['labelCol']]) {
                $target = $labelSebelum; // label hasil merge vertikal
            }
            $labelSebelum = $target;
            if (! $target) {
                continue;
            }

            foreach ($blok['tanggal'] as $c => $t) {
                $ku1 = $this->teks($row[$c] ?? null);
                $ku2 = $this->teks($row[$c + 1] ?? null);
                $gabung = isset($mergeId[$r][$c]) && ($mergeId[$r][$c] === ($mergeId[$r][$c + 1] ?? null));

                if ($target[0] === 'info') {
                    if ($ku1 !== '' && empty($hasil[$t]['info'][$target[1]])) {
                        $hasil[$t]['info'][$target[1]] = mb_substr($ku1, 0, 255);
                    }

                    continue;
                }
                if ($gabung) {
                    $ku1 !== '' && $hasil[$t]['petugas'][] = ['peran' => $target[1], 'kebaktian' => null, 'nama' => $ku1];

                    continue;
                }
                $ku1 !== '' && $hasil[$t]['petugas'][] = ['peran' => $target[1], 'kebaktian' => 'KU I', 'nama' => $ku1];
                $ku2 !== '' && $hasil[$t]['petugas'][] = ['peran' => $target[1], 'kebaktian' => 'KU II', 'nama' => $ku2];
            }
        }

        return $hasil;
    }

    private function label(string $teks): ?array
    {
        $t = mb_strtolower($teks);
        foreach (self::LABEL as $kunci => $target) {
            if (str_contains($t, $kunci)) {
                return $target;
            }
        }

        return null;
    }

    private function teks(mixed $v): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $v));
    }

    public function tanggal(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && $v > 30000 && $v < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString();
        }
        if (! is_string($v) || ! preg_match('/(\d{1,2})[\s\-\/]+([a-z]+)[\s\-\/]+(\d{4})/i', $v, $m)) {
            return null;
        }
        $bulan = self::BULAN[mb_strtolower($m[2])] ?? mb_strtolower($m[2]);
        try {
            return Carbon::createFromFormat('j F Y', "{$m[1]} {$bulan} {$m[3]}")->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
