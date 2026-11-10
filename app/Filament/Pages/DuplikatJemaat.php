<?php

namespace App\Filament\Pages;

use App\Models\Member;
use App\Services\DuplicateFinder;
use App\Support\HakAkses;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** DJ-10: daftar kemungkinan duplikat dengan opsi gabung. */
class DuplikatJemaat extends Page
{
    protected string $view = 'filament.pages.duplikat-jemaat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|\UnitEnum|null $navigationGroup = 'Jemaat';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Kemungkinan Duplikat';

    public static function canAccess(): bool
    {
        return HakAkses::boleh('duplikat-jemaat');
    }

    /** Menu hanya tampil bila ada kemungkinan duplikat, dengan badge merah jumlahnya. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && app(DuplicateFinder::class)->jumlah() > 0;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) app(DuplicateFinder::class)->jumlah();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pasangan data jemaat yang kemungkinan sama orangnya';
    }

    public function merge(int $keepId, int $removeId): void
    {
        $keep = Member::findOrFail($keepId);
        $remove = Member::findOrFail($removeId);
        app(DuplicateFinder::class)->merge($keep, $remove);
        Notification::make()->success()->title("{$remove->code} digabung ke {$keep->code}")->send();
    }

    protected function getViewData(): array
    {
        return ['pairs' => app(DuplicateFinder::class)->allPairs()];
    }
}
