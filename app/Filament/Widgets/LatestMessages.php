<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Les cinq derniers messages reçus par le formulaire de contact (visible seulement avec le droit de les lire). */
class LatestMessages extends TableWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('messages.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Derniers messages')
            ->query(fn () => ContactMessage::query()->latest()->limit(5))
            ->columns([
                TextColumn::make('name')->label('De')->weight(fn (ContactMessage $record) => $record->read_at ? null : 'bold')->limit(24),
                TextColumn::make('subject')->label('Sujet')->limit(34),
                TextColumn::make('created_at')->label('Reçu')->since(),
            ])
            ->recordUrl(fn (ContactMessage $record) => ContactMessageResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->deferLoading(false)
            ->headerActions([
                Action::make('all')->label('Tout voir')->icon(Heroicon::OutlinedArrowRight)->color('gray')->url(ContactMessageResource::getUrl()),
            ])
            ->emptyStateHeading('Aucun message pour le moment')
            ->emptyStateIcon(Heroicon::OutlinedEnvelope);
    }
}
