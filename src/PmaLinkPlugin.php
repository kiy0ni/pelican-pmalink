<?php

namespace KiyOni\PmaLink;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use KiyOni\PmaLink\Controllers\PmaController;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

// Les bons namespaces trouvés !
use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;

class PmaLinkPlugin implements Plugin, HasPluginSettings
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'pmalink';
    }

    public function register(Panel $panel): void
    {
    }

    public function boot(Panel $panel): void
    {
        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/pmalink/redirect/{database}', [PmaController::class, 'redirect'])->name('pmalink.redirect');
        });

        $verifyScriptPath = public_path('sso-verify.php');
        if (!file_exists($verifyScriptPath)) {
            $scriptContent = <<<'SCRIPT'
<?php
$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token']);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$keysToTry = [$token, 'pmalink_' . $token, 'pma_sso_' . $token, 'pma_token_' . $token];
$data = null;
$foundKey = null;

foreach ($keysToTry as $key) {
    $data = \Illuminate\Support\Facades\Cache::get($key);
    if ($data) {
        $foundKey = $key;
        break;
    }
}

if ($data) {
    \Illuminate\Support\Facades\Cache::forget($foundKey);
    http_response_code(200);
    header('Content-Type: application/json');
    echo is_string($data) ? $data : json_encode($data);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Token not found']);
SCRIPT;
            file_put_contents($verifyScriptPath, $scriptContent);
        }
    }

    public function getSettingsFormData(): array
    {
        return config("pmalink") ?? [];
    }

    public function getSettingsForm(): array
    {
        return [
            TextInput::make('pma_url')
                ->label('phpMyAdmin URL')
                ->placeholder('https://phpmyadmin.your-domain.com')
                ->required()
                ->url()
                ->default(fn () => env('PMALINK_PMA_URL')),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'PMALINK_PMA_URL' => rtrim($data['pma_url'], '/'),
        ]);

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
