<?php

namespace App\Exports;

use App\Models\Member;
use App\Support\Opsi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor data jemaat. Kolom HP & alamat hanya untuk Super Admin, Sekretariat, Pendeta.
 */
class MembersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Collection $members, private bool $withContacts) {}

    public function collection(): Collection
    {
        return $this->members;
    }

    public function headings(): array
    {
        $h = ['Kode', 'No Induk', 'Nama', 'Alias', 'Kelamin', 'Tanggal Lahir', 'Umur', 'ID KK', 'Hubungan', 'Wilayah',
            'Status Keanggotaan', 'Status Keaktifan', 'Tgl Pernikahan', 'Komisi'];
        if ($this->withContacts) {
            array_push($h, 'No. HP', 'Alamat');
        }

        return $h;
    }

    /** @param Member $m */
    public function map($m): array
    {
        $row = [
            $m->code, $m->old_registration_no, $m->name, $m->alias, $m->gender,
            $m->birth_date?->format('d/m/Y'), $m->age, $m->family?->code,
            Opsi::label(Opsi::HUBUNGAN, $m->family_relation), $m->family?->region?->name,
            Opsi::label(Opsi::KEANGGOTAAN, $m->membership_status), Opsi::label(Opsi::KEAKTIFAN, $m->activity_status),
            $m->marriage_date?->format('d/m/Y'), $m->commissions->pluck('name')->join(', '),
        ];
        if ($this->withContacts) {
            array_push($row, $m->phone ?? $m->family?->phone, $m->family?->address);
        }

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
