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

        // Bulan & tahun dari judul ("Bulan : Maret 2026"): melengkapi tanggal tanpa tahun dan
        // mengabaikan kolom sisa salinan dari bulan lain (mis. "30 Agustus" di sheet September).
        $judul = null;
        foreach (array_slice($rows, 0, 3) as $row) {
            foreach ($row as $v) {
                if (is_string($v) && preg_match("/bulan[^:]*:\s*([a-z]+)\s+(\d{4})/i", $v, $m) && ($n = $this->nomorBulan($m[1]))) {
                    $judul = [$n, (int) $m[2]];
                    break 2;
                }
            }
        }

        $hasil = [];
        $blok = null; // ["labelCol" => int, "tanggal" => [col => Y-m-d], "gabungan" => [col => judul ibadah]]
        $labelSebelum = null;
        foreach ($rows as $r => $row) {
            // Baris "Tanggal" memulai blok baru.
            foreach ($row as $c => $v) {
                if (is_string($v) && preg_match("/^\s*tanggal\s*$/i", $v)) {
                    $tgl = [];
                    $gabungan = [];
                    foreach ($row as $c2 => $v2) {
                        if ($c2 <= $c || ! ($t = $this->tanggal($v2, $judul[1] ?? null)) || in_array($t, $tgl, true)) {
                            continue;
                        }
                        if ($judul && (int) substr($t, 5, 2) !== $judul[0]) {
                            continue;
                        }
                        if ($judul) {
                            $t = $judul[1].substr($t, 4); // tahun ikut judul sheet (sel tanggal kadang salinan tahun lalu)
                            if (in_array($t, $tgl, true)) {
                                continue;
                            }
                        }
                        $tgl[$c2] = $t;
                        // Baris di bawah tanggal: "Ibadah Umum I / II", atau satu ibadah (Gabungan, Paska, Komisi ...).
                        $jenis = $this->teks(preg_replace("/\s*\/?\s*pukul.*$/is", "", (string) ($rows[$r + 1][$c2] ?? "")));
                        $satu = isset($mergeId[$r + 1][$c2]) && $mergeId[$r + 1][$c2] === ($mergeId[$r + 1][$c2 + 1] ?? null);
                        if (stripos($jenis, "gabungan") !== false || ($satu && stripos($jenis, "umum") === false)) {
                            $gabungan[$c2] = $jenis ?: "Ibadah Gabungan";
                        }
                    }
                    // Baris kedua sel "Tanggal" yang di-merge (Ibadah Umum I/II) tidak memulai blok baru.
                    if ($tgl) {
                        $blok = ["labelCol" => $c, "tanggal" => $tgl, "gabungan" => $gabungan];
                        foreach ($tgl as $c2 => $t) {
                            $hasil[$t] = ["info" => [], "petugas" => []];
                            if (isset($gabungan[$c2]) && strcasecmp($gabungan[$c2], "Ibadah Gabungan") !== 0) {
                                $hasil[$t]["ibadah"] = $gabungan[$c2];
                            }
                        }
                    }
                    $labelSebelum = null;

                    continue 2;
                }
            }
            if (! $blok) {
                continue;
            }

            $labelTeks = trim((string) ($row[$blok["labelCol"]] ?? ""));
            $target = $labelTeks === "" ? $labelSebelum : $this->label($labelTeks);
            if ($labelTeks !== "" && ! $target && $labelSebelum && ($rows[$r - 1][$blok["labelCol"]] ?? null) === $row[$blok["labelCol"]]) {
                $target = $labelSebelum; // label hasil merge vertikal
            }
            $labelSebelum = $target;
            if (! $target) {
                continue;
            }

            foreach ($blok["tanggal"] as $c => $t) {
                $ku1 = $this->teks($row[$c] ?? null);
                $ku2 = $this->teks($row[$c + 1] ?? null);
                $gabung = isset($mergeId[$r][$c]) && ($mergeId[$r][$c] === ($mergeId[$r][$c + 1] ?? null));

                if ($target[0] === "info") {
                    if ($ku1 !== "" && empty($hasil[$t]["info"][$target[1]])) {
                        $hasil[$t]["info"][$target[1]] = mb_substr($ku1, 0, 255);
                    }

                    continue;
                }
                if (isset($blok["gabungan"][$c])) {
                    foreach ($gabung ? [$ku1] : [$ku1, $ku2] as $nama) {
                        $nama !== "" && $hasil[$t]["petugas"][] = ["peran" => $target[1], "kebaktian" => "Gabungan", "nama" => $nama];
                    }

                    continue;
                }
                if ($gabung) {
                    $ku1 !== "" && $hasil[$t]["petugas"][] = ["peran" => $target[1], "kebaktian" => null, "nama" => $ku1];

                    continue;
                }
                $ku1 !== "" && $hasil[$t]["petugas"][] = ["peran" => $target[1], "kebaktian" => "KU I", "nama" => $ku1];
                $ku2 !== "" && $hasil[$t]["petugas"][] = ["peran" => $target[1], "kebaktian" => "KU II", "nama" => $ku2];
            }
        }

        // Nama ibadah khusus (Paska, Komisi Anak, ...) disimpan di keterangan.
        foreach ($hasil as $t => $j) {
            if (isset($j["ibadah"])) {
                $hasil[$t]["info"]["keterangan"] = mb_substr(trim($j["ibadah"]." · ".($j["info"]["keterangan"] ?? ""), " ·"), 0, 255);
                unset($hasil[$t]["ibadah"]);
            }
        }

        return $hasil;
    }
    private function label(string $teks): ?array
    {
        $t = mb_strtolower($teks);
        if (mb_strlen($t) > 40) {
            return null; // catatan panjang di bawah jadwal, bukan label baris
        }
        foreach (self::LABEL as $kunci => $target) {
            if (str_contains($t, $kunci)) {
                return $target;
            }
        }

        return null;
    }

    private function teks(mixed $v): string
    {
        return trim(preg_replace("/\s+/u", " ", (string) $v));
    }

    /** "Maret", "mar", "March", "agust", "Des" -> 3 / 8 / 12. */
    private function nomorBulan(string $nama): ?int
    {
        $n = mb_strtolower($nama);
        $en = self::BULAN[$n] ?? $n;
        foreach (array_values(array_unique(self::BULAN)) as $i => $bulan) {
            if (str_starts_with($bulan, $en) && mb_strlen($en) >= 3) {
                return $i + 1;
            }
        }
        foreach (array_keys(self::BULAN) as $id) {
            if (mb_strlen($n) >= 3 && str_starts_with($id, $n)) {
                return $this->nomorBulan($id);
            }
        }

        return null;
    }

    /** Tanggal dari sel Excel: angka tanggal Excel, "01 November 2026", "1 Maret" (+ tahun default), "4-Jan". */
    public function tanggal(mixed $v, ?int $tahun = null): ?string
    {
        if ($v === null || $v === "") {
            return null;
        }
        if (is_numeric($v) && $v > 30000 && $v < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString();
        }
        if (! is_string($v) || ! preg_match("/^\s*(\d{1,2})[\s\-\/]+([a-z]+)\.?(?:[\s\-\/,]+(\d{4}))?\s*$/i", $v, $m)) {
            return null;
        }
        $bulan = $this->nomorBulan($m[2]);
        $tahun = ! empty($m[3]) ? (int) $m[3] : ($tahun ?? (int) now()->year);
        if (! $bulan || ! checkdate($bulan, (int) $m[1], $tahun)) {
            return null;
        }

        return sprintf("%04d-%02d-%02d", $tahun, $bulan, (int) $m[1]);
    }
}
