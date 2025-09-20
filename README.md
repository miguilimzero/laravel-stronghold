# Laravel Stronghold

Laravel Stronghold is an extended version of Laravel Fortify that adds profile management, social authentication, and enhanced security features to your Laravel application. It provides a robust authentication foundation with OAuth support, new location confirmation, and user profile management out of the box.

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Enabling Features](#enabling-features)
  - [OAuth Authentication](#oauth-authentication)
  - [User Traits](#user-traits)
  - [Customizing Views](#customizing-views)
  - [Custom New Location Detection](#custom-new-location-detection)
- [License](#license)

## Installation

You can install the package via composer:

```sh
composer require miguilim/laravel-stronghold
```

After installation, run the install command:

```sh
php artisan stronghold:install
```

This will publish the configuration file, migrations, and action stubs.

Run the migrations:

```sh
php artisan migrate
```

## Configuration

First, add the OAuth provider configurations to your `config/services.php` file:

```php
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => '/oauth/github/callback',
],

'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => '/oauth/google/callback',
],

// Add other providers as needed...
```

Then add the corresponding environment variables to your `.env` file:

```env
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

# Add other providers as needed...
```

## Usage

### Enabling Features

Configure which features to enable in `config/stronghold.php`:

```php
'features' => [
    'confirm-new-location',
    'sign-in-notification',
    'socialite',
],
```

### OAuth Authentication

Users can authenticate using OAuth providers:

```
/oauth/{provider}         # Redirect to OAuth provider
/oauth/{provider}/callback # Handle OAuth callback
```

### User Traits

Add the provided traits to your User model to enable additional functionality:

```php
use Miguilim\LaravelStronghold\Traits\HasConnectedAccounts;
use Miguilim\LaravelStronghold\Traits\HasProfilePhoto;

class User extends Authenticatable
{
    use HasConnectedAccounts;
    use HasProfilePhoto;

    // Your existing model code...
}
```

### Customizing Views

Register custom views in your `AppServiceProvider`:

```php
use Miguilim\LaravelStronghold\Stronghold;

Stronghold::profileView(function () {
    return view('profile.show');
});

Stronghold::confirmNewLocationView('auth.confirm-location'); // Optional, only if you will use the feature
```

### Custom New Location Detection

Define custom logic for detecting new locations:

```php
use Miguilim\LaravelStronghold\Stronghold;

Stronghold::detectNewLocationUsing(function ($request, $user) {
    return true; // true if it is a new location (default is always true)
});
```

## License

Laravel Stronghold is open-sourced software licensed under the [MIT license](LICENSE).
