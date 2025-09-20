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
     * Get a connected account by provider.
     */
    public function getConnectedAccount(string $provider): ?ConnectedAccount
    {
        return $this->connectedAccounts()
            ->where('provider', $provider)
            ->first();
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
}
