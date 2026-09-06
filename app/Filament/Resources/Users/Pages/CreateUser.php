<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // A user created from within a tenant's Team page is attached to that
    // tenant immediately (role: member) — otherwise they'd be created with
    // no company membership and invisible to UserResource's own tenant
    // scoping.
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = static::getModel()::create($data);

        if (Filament::hasTenancy() && ($tenant = Filament::getTenant())) {
            $user->companies()->attach($tenant, ['role' => 'member']);
        }

        return $user;
    }
}
