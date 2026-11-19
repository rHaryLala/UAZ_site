<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Un compte est actif tant qu'on ne l'a pas désactivé (un compte qui vient d'être créé, sans la colonne chargée, l'est aussi). */
    public function isActive(): bool
    {
        return $this->is_active !== false;
    }

    /**
     * Les super-administrateurs ne se désactivent pas (même garde-fou que pour la suppression : on ne doit jamais pouvoir verrouiller le
     * back-office par erreur), et personne ne désactive son propre compte.
     */
    public function canBeDeactivated(): bool
    {
        return ! $this->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN) && ! $this->is(auth()->user());
    }

    /**
     * Tout compte actif doté d'au moins un rôle peut entrer dans le back-office. Cette vérification se refait à chaque requête : désactiver un compte
     * coupe aussi la session qu'il a déjà ouverte.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && $this->roles()->exists();
    }
}
