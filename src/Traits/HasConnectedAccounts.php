<?php

namespace Miguilim\LaravelStronghold\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;

trait HasConnectedAccounts
{
    /**
     * Get the connected accounts for the user.
     */
    public function connectedAccounts(): HasMany
    {
        return $this->hasMany(ConnectedAccount::class);
    }

    /**
     * Determine if the user has any connected accounts.
     */
    public function hasConnectedAccounts(): bool
    {
        return $this->connectedAccounts()->exists();
    }

    /**
     * Get a connected account by provider.
     */
    public function getConnectedAccount(string $provider): ?ConnectedAccount
    {
        return $this->connectedAccounts()
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Determine if the user has a connected account for the given provider.
     */
    public function hasConnectedAccount(string $provider): bool
    {
        return $this->connectedAccounts()
            ->where('provider', $provider)
            ->exists();
    }

    /**
     * Get all providers the user has connected.
     */
    public function getConnectedProviders(): array
    {
        return $this->connectedAccounts()
            ->pluck('provider')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Determine if the user can disconnect the given account.
     */
    public function canDisconnectAccount(string $provider): bool
    {
        // User can disconnect if they have a password or more than one connected account
        return $this->password !== null || $this->connectedAccounts()->count() > 1;
    }

    /**
     * Determine if the user needs to set a password.
     */
    public function needsPassword(): bool
    {
        return $this->password === null;
    }
}
