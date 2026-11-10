<?php

namespace App\Filament\Resources\Commissions\RelationManagers;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Anggota Komisi';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->peran('super_admin', 'sekretariat');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')->label('Kode'),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('age')->label('Umur'),
                TextColumn::make('gender')->label('L/P'),
                TextColumn::make('family.region.name')->label('Wilayah'),
                TextColumn::make('pivot.joined_at')->label('Bergabung')->date('d/m/Y'),
                IconColumn::make('pivot.is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                AttachAction::make()->label('Tambah anggota')->recordSelectSearchColumns(['name', 'alias', 'code'])
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        DatePicker::make('joined_at')->label('Bergabung')->default(today()),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ]),
                // DJ-12: sistem mengusulkan, admin yang menetapkan.
                Action::make('usulan')->label('Usulan dari umur & kelamin')->icon(Heroicon::OutlinedSparkles)
                    ->modalHeading('Tetapkan anggota komisi dari usulan')
                    ->schema(function () {
                        $c = $this->getOwnerRecord();
                        $sudah = $c->members()->pluck('members.id');
                        $opsi = Member::tercatat()->whereNotIn('id', $sudah)->orderBy('name')->get()
                            ->filter(fn (Member $m) => $c->matches($m))
                            ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->name} ({$m->age} th, {$m->gender})"]);

                        return [
                            CheckboxList::make('member_ids')->label($opsi->count().' jemaat cocok dengan aturan')
                                ->options($opsi)->default($opsi->keys()->all())->bulkToggleable()->columns(2)->searchable(),
                        ];
                    })
                    ->action(function (array $data) {
                        $ids = collect($data['member_ids'] ?? []);
                        $this->getOwnerRecord()->members()->syncWithoutDetaching(
                            $ids->mapWithKeys(fn ($id) => [$id => ['joined_at' => today(), 'is_active' => true]])->all());
                        Notification::make()->success()->title($ids->count().' anggota ditambahkan')->send();
                    }),
            ])
            ->recordActions([DetachAction::make()->label('Keluarkan')])
            ->toolbarActions([BulkActionGroup::make([DetachBulkAction::make()])]);
    }
}
