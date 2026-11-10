<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/** Login memakai username + password. */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->helperText(fn () => self::adaKolomUsername() ? null : 'Database belum diperbarui: masuk dengan email, lalu jalankan Update database di menu Pemeliharaan.')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = mb_strtolower(trim($data['username']));
        // Email tetap diterima: dipakai juga sebelum migrasi kolom username dijalankan di server.
        $kolom = (str_contains($login, '@') || ! self::adaKolomUsername()) ? 'email' : 'username';

        return [
            $kolom => $login,
            'password' => $data['password'],
        ];
    }

    private static function adaKolomUsername(): bool
    {
        return once(fn () => Schema::hasColumn('users', 'username'));
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => 'Username atau password salah.',
        ]);
    }
}
