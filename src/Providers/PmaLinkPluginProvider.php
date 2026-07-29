<?php

namespace KiyOni\PmaLink\Providers;

use App\Filament\Server\Resources\Databases\DatabaseResource;
use App\Models\Database;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class PmaLinkPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        DatabaseResource::modifyTable(function (Table $table): Table {
            $pmaAction = Action::make('open_pma')
                ->label('phpMyAdmin')
                ->icon('tabler-database-export')
                ->color('primary')
                ->visible(fn () => filled(config('pmalink.pma_url'))
                    && filled(config('pmalink.verify_secret')))
                ->url(fn (Database $record) => route('pmalink.redirect', ['database' => $record->getKey()]))
                ->openUrlInNewTab();

            return $table->recordActions([
                $pmaAction,
                ...array_values($table->getRecordActions()),
            ]);
        });
    }
}
