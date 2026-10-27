<?php

namespace App\Filament\Widgets;

use App\Enums\PublicationStatus;
use App\Filament\Resources\NewsPosts\NewsPostResource;
use App\Models\NewsPost;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Les cinq dernières actualités en brouillon : ce qu'il reste à relire et à publier. */
class LatestDrafts extends TableWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return NewsPostResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Brouillons à relire')
            ->query(fn () => NewsPost::query()->where('status', PublicationStatus::Draft)->latest('updated_at')->limit(5))
            ->columns([
                TextColumn::make('title')->label('Titre')->limit(48)->weight('medium'),
                TextColumn::make('updated_at')->label('Modifié')->since(),
            ])
            ->recordUrl(fn (NewsPost $record) => NewsPostResource::getUrl('edit', ['record' => $record]))
            ->paginated(false)
            ->deferLoading(false)
            ->headerActions([
                Action::make('all')->label('Tout voir')->icon(Heroicon::OutlinedArrowRight)->color('gray')
                    ->url(NewsPostResource::getUrl('index', ['filters' => ['publication' => ['value' => 'draft']]])),
            ])
            ->emptyStateHeading('Aucun brouillon en attente')
            ->emptyStateDescription('Toutes les actualités sont publiées.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
