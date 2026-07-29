<?php

namespace KiyOni\PmaLink;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Support\Str;

class PmaLinkPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'pmalink';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    public function getSettingsFormData(): array
    {
        $data = config('pmalink') ?? [];
        $data['verify_secret'] ??= Str::random(64);

        return $data;
    }

    public function getSettingsForm(): array
    {
        return [
            TextInput::make('pma_url')
                ->label('phpMyAdmin URL')
                ->placeholder('https://phpmyadmin.your-domain.com')
                ->required()
                ->url(),
            TextInput::make('verify_secret')
                ->label('Verification secret')
                ->password()
                ->revealable()
                ->helperText('Copy this into the X-PmaLink-Secret header of your signon.php.')
                ->required()
                ->minLength(32),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'PMALINK_PMA_URL' => rtrim($data['pma_url'], '/'),
            'PMALINK_VERIFY_SECRET' => $data['verify_secret'],
        ]);

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
