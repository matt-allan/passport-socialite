## Laravel Passport Socialite Driver
[![Packagist License](https://poser.pugx.org/matt-allan/passport-socialite/license.png)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://poser.pugx.org/matt-allan/passport-socialite/version.png)](https://packagist.org/packages/matt-allan/passport-socialite)
[![Build Status](https://travis-ci.com/matt-allan/passport-socialite.svg?branch=master)](https://travis-ci.com/matt-allan/passport-socialite)

A [Laravel Socialite](https://laravel.com/docs/5.8/socialite) driver for authenticating with [Laravel Passport](https://laravel.com/docs/5.8/passport) OAuth servers.

## Installation

This package can be installed using Composer. The Socialite package will also be installed if it is not already installed.

```
composer require matt-allan/passport-socialite
```

## Configuration

Before using this driver, you will need to add credentials for the Passport server. These credentials should be placed in your `config/services.php` configuration file, and should use the key `passport`. For example:

```php
'passport' => [
    'client_id' => env('PASSPORT_CLIENT_ID'),
    'client_secret' => env('PASSPORT_CLIENT_SECRET'),
    'url' => env('PASSPORT_URL'),
    'redirect' => env('PASSPORT_REDIRECT'),
],
``` 

## Usage

The Passport driver works identically to the other Socialite drivers. All of the methods mentioned in the [official documentation](https://laravel.com/docs/5.8/socialite) are available.

You can access the passport driver using the `Socialite` facade:

```php
<?php

namespace App\Http\Controllers\Auth;

use Socialite;

class LoginController extends Controller
{
    /**
     * Redirect the user to the Passport server's authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('passport')->redirect();
    }

    /**
     * Obtain the user information from the Passport server.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback()
    {
        $user = Socialite::driver('passport')->user();

        // $user->token;
    }
}
```

The current user's details will be retrieved from the default `api/user` route.

In addition to the standard Socialite methods a `refresh` method is available to easily refresh expired tokens. The `refresh` method accepts a refresh token and returns an updated `User` with new access and refresh tokens if the token is refreshed successfully.

```php
$user = Socialite::driver('passport')->refresh($refreshToken);
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](./github/CONTRIBUTING.md) for details.

## Credits

- [Matt Allan](https://github.com/matt-allan)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
