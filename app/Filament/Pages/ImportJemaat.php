<?php

namespace App\Filament\Pages;

use App\Exports\ArrayExport;
use App\Jobs\GeocodeFamily;
use App\Models\Family;
use App\Services\DuplicateFinder;
use App\Services\MemberImporter;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/** DJ-09: import Excel dengan pemetaan kolom, pratinjau, dan laporan baris gagal. */
class ImportJemaat extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.import-jemaat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Jemaat';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Import Data Jemaat';

    public $file;

    public ?string $storedPath = null;

    public int $sheet = 0;

    public array $sheets = [];

    public array $sheetNames = [];

    public array $headings = [];

    public array $mapping = [];

    public int $rowCount = 0;

    public int $headerRow = 1;

    public array $preview = [];

    public array $previewStats = [];

    public bool $createRegions = true;

    public bool $placeholderSacraments = true;

    public bool $geocode = true;

    public ?array $result = null;

    public static function canAccess(): bool
    {
        return auth()->user()->peran('super_admin', 'sekretariat');
    }

    public function updatedFile(): void
    {
        $this->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:20480']);
        $this->storedPath = $this->file->store('imports', 'local');
        $this->sheet = 0;
        $this->loadSheet();
    }

    public function updatedSheet(): void
    {
        $this->loadSheet();
    }

    protected function fullPath(): string
    {
        return \Storage::disk('local')->path($this->storedPath);
    }

    protected function loadSheet(): void
    {
        $imp = app(MemberImporter::class);
        $data = $imp->read($this->fullPath(), $this->sheet);
        $this->sheets = $data['sheets'];
        $this->sheetNames = $data['sheet_names'];
        $this->headings = $data['headings'];
        $this->rowCount = count($data['rows']);
        $this->headerRow = $data['header_row'];
        $this->mapping = array_map(fn ($v) => $v === null ? '' : (string) $v, $imp->guessMapping($this->headings));
        $this->preview = [];
        $this->result = null;
    }

    protected function cleanMapping(): array
    {
        return array_map(fn ($v) => $v === '' || $v === null ? null : (int) $v, $this->mapping);
    }

    public function runPreview(): void
    {
        if (($this->mapping['name'] ?? '') === '') {
            Notification::make()->danger()->title('Kolom Nama wajib dipetakan')->send();

            return;
        }
        $imp = app(MemberImporter::class);
        $rows = $imp->read($this->fullPath(), $this->sheet)['rows'];
        $all = $imp->preview($rows, $this->cleanMapping(), app(DuplicateFinder::class));
        $this->previewStats = [
            'total' => count($all),
            'error' => count(array_filter($all, fn ($r) => $r['errors'])),
            'warning' => count(array_filter($all, fn ($r) => $r['warnings'])),
            'duplikat' => count(array_filter($all, fn ($r) => collect($r['warnings'])->contains(fn ($w) => str_starts_with($w, 'Kemungkinan duplikat')))),
        ];
        // Tampilkan baris bermasalah lebih dulu, maksimal 100.
        usort($all, fn ($a, $b) => (count($b['errors']) * 10 + count($b['warnings'])) <=> (count($a['errors']) * 10 + count($a['warnings'])));
        $this->preview = array_map(fn ($r) => [
            'line' => $r['line'] + $this->headerRow,
            'code' => $r['data']['code'] ?? null,
            'name' => $r['data']['name'] ?? null,
            'birth_date' => $r['data']['birth_date'] ?? null,
            'region' => $r['data']['region_raw'] ?? null,
            'family' => $r['data']['family_code'] ?? null,
            'status' => ($r['data']['membership_status'] ?? '').' / '.($r['data']['activity_status'] ?? '-'),
            'errors' => $r['errors'],
            'warnings' => $r['warnings'],
        ], array_slice($all, 0, 100));
    }

    public function runImport(): void
    {
        $imp = app(MemberImporter::class);
        $rows = $imp->read($this->fullPath(), $this->sheet)['rows'];
        $res = $imp->import($rows, $this->cleanMapping(), [
            'create_regions' => $this->createRegions,
            'placeholder_sacraments' => $this->placeholderSacraments,
            'geocode' => false,
        ]);
        $res['failed'] = array_map(fn ($f) => ['line' => $f['line'] + $this->headerRow] + $f, $res['failed']);

        if ($this->geocode) {
            $ids = Family::whereIn('geocode_status', ['belum'])->whereNotNull('address')->pluck('id');
            $ids->each(fn ($id) => GeocodeFamily::dispatch($id));
            $res['geocode_queued'] = $ids->count();
        }
        $this->result = $res;
        Notification::make()->success()->title("Import selesai: {$res['created']} baru, {$res['updated']} diperbarui, ".count($res['failed']).' gagal')->send();
    }

    public function downloadFailed()
    {
        return Excel::download(new ArrayExport(['Baris', 'Nama', 'Kesalahan'],
            array_map(fn ($f) => [$f['line'], $f['name'], $f['error']], $this->result['failed'] ?? []), 'Baris gagal'),
            'import-gagal-'.now()->format('Ymd-His').'.xlsx');
    }

    protected function getViewData(): array
    {
        return ['fields' => MemberImporter::FIELDS];
    }
}
