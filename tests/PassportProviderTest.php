<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use GuzzleHttp\Handler\MockHandler;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use MattAllan\PassportSocialite\PassportSocialiteServiceProvider;

class PassportProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PassportSocialiteServiceProvider::class,
            SocialiteServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('services.passport', [
            'client_id' => 1,
            'client_secret' => 'secret123',
            'url' => 'passport.test',
            'redirect' => 'app.test/login/passport/callback',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerLoginRoutes();
    }

    public function test_redirect()
    {
        $response = $this->call('GET', 'login/passport');

        $url = $response->getTargetUrl();

        $this->assertEquals('passport.test/oauth/authorize', parse_url($url, PHP_URL_PATH));

        parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);

        $this->assertArrayHasKey('client_id', $queryParams);
        $this->assertEquals('1', $queryParams['client_id']);

        $this->assertArrayHasKey('redirect_uri', $queryParams);
        $this->assertEquals('app.test/login/passport/callback', $queryParams['redirect_uri']);

        $this->assertArrayHasKey('scope', $queryParams);
        $this->assertEquals('read:user write:user', $queryParams['scope']);

        $this->assertArrayHasKey('response_type', $queryParams);
        $this->assertEquals('code', $queryParams['response_type']);

        $this->assertArrayHasKey('state', $queryParams);
        $this->assertEquals(40, strlen($queryParams['state']));
        $this->assertEquals(session('state'), $queryParams['state']);
    }

    public function test_callback()
    {
        $this->fakePassportResponse();

        $state = Str::random(40);

        $response = $this
            ->withSession(['state' => $state])
            ->call('GET', 'login/passport/callback', [
                'state' => $state,
                'code' => 'some-code',
            ]);

        $user = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $user);
        $this->assertEquals(123, $user['id']);

        $this->assertArrayHasKey('name', $user);
        $this->assertEquals('Matt Allan', $user['name']);

        $this->assertArrayHasKey('nickname', $user);
        $this->assertEquals('matt@passport.test', $user['nickname']);

        $this->assertArrayHasKey('email', $user);
        $this->assertEquals('matt@passport.test', $user['email']);

        $this->assertArrayHasKey('avatar', $user);
        $this->assertEquals(null, $user['avatar']);

        $this->assertArrayHasKey('token', $user);
        $this->assertEquals('access', $user['token']);

        $this->assertArrayHasKey('refreshToken', $user);
        $this->assertEquals('refresh', $user['refreshToken']);

        $this->assertArrayHasKey('expiresIn', $user);
        $this->assertEquals(999, $user['expiresIn']);
    }

    private function registerLoginRoutes()
    {
        $this->app['router']->get('login/passport/', ['middleware' => 'web', 'uses' => function () {
            return Socialite::driver('passport')
                ->scopes(['read:user', 'write:user'])
                ->redirect();
        }]);

        $this->app['router']->get('login/passport/callback', ['middleware' => 'web', 'uses' => function () {
            $user = Socialite::driver('passport')->user();

            return [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'token' => $user->token,
                'refreshToken' => $user->refreshToken,
                'expiresIn' => $user->expiresIn,
            ];
        }]);
    }

    private function fakePassportResponse()
    {
        // We can't resolve the socialite driver until the request is bound
        $this->app->rebinding('request', function () {
            Socialite::driver('passport')->setHttpClient(
                new Client(['handler' => new MockHandler([
                    new Response(200, [], json_encode([
                        'token_type' => 'Bearer',
                        'expires_in' => 999,
                        'access_token' => 'access',
                        'refresh_token' => 'refresh',
                    ])),
                    new Response(200, [], json_encode([
                        'id' => 123,
                        'name' => 'Matt Allan',
                        'email' => 'matt@passport.test',
                    ])),
                ])])
            );
        });
    }
}
