<?php

namespace App\Filament\Pages\Settings\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns a Filament Page into a "load one record, edit it, save it" settings
 * screen — the same shape as Filament's own EditTenantProfile, generalized
 * to any per-tenant settings record (Company itself for numbering, or a
 * dedicated CompanySetting row for everything else). See
 * docs/filament-admin-layout-design.md §3 for why these are Pages, not
 * Resources: each is a singleton per tenant, never a list.
 *
 * @property-read Schema $form
 */
trait InteractsWithSettingsRecord
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?Model $record = null;

    public function mount(): void
    {
        $this->record = $this->resolveRecord();

        $this->form->fill($this->record->attributesToArray());
    }

    abstract protected function resolveRecord(): Model;

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->model($this->record)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->record->update($data);

        Notification::make()
            ->success()->seconds(4)
            ->title('Saved')
            ->send();
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->key('form-actions'),
            ]);
    }
}
