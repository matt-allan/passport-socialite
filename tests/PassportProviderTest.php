<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase;
use GuzzleHttp\Handler\MockHandler;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\TestResponse;
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

        $app['config']->set('app.debug', true);
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

        $this->assertEquals([
            'id' => 123,
            'name' => 'Matt Allan',
            'nickname' => 'matt@passport.test',
            'email' => 'matt@passport.test',
            'avatar' => null,
            'token' => 'access',
            'refreshToken' => 'refresh',
            'expiresIn' => 999,
            'user' => [
                'id' => 123,
                'name' => 'Matt Allan',
                'email' => 'matt@passport.test',
            ],
        ], $user);
    }

    public function test_refresh()
    {
        $this->fakePassportResponse();

        $response = $this->call('GET', 'login/passport/refresh');

        $this->assertResponseContainsUser($response);
    }

    private function registerLoginRoutes()
    {
        $this->app['router']->get('login/passport/', ['middleware' => 'web', 'uses' => function () {
            return Socialite::driver('passport')
                ->scopes(['read:user', 'write:user'])
                ->redirect();
        }]);

        $this->app['router']->get('login/passport/callback', ['middleware' => 'web', 'uses' => function () {
            return json_encode(Socialite::driver('passport')->user());
        }]);

        $this->app['router']->get('login/passport/refresh', ['middleware' => 'web', 'uses' => function () {
            return json_encode(Socialite::driver('passport')->refresh('abc123'));
        }]);
    }

    private function fakePassportResponse()
    {
        // We can't resolve the socialite driver until the request is bound
        $this->app->rebinding('request', function () {
            Socialite::driver('passport')->setHttpClient(
                new Client(['handler' => new MockHandler([
                    new PsrResponse(200, [], json_encode([
                        'token_type' => 'Bearer',
                        'expires_in' => 999,
                        'access_token' => 'access',
                        'refresh_token' => 'refresh',
                    ])),
                    new PsrResponse(200, [], json_encode([
                        'id' => 123,
                        'name' => 'Matt Allan',
                        'email' => 'matt@passport.test',
                    ])),
                ])])
            );
        });
    }

    private function assertResponseContainsUser(TestResponse $response)
    {
        $user = json_decode($response->getContent(), true);

        $this->assertEquals([
            'id' => 123,
            'name' => 'Matt Allan',
            'nickname' => 'matt@passport.test',
            'email' => 'matt@passport.test',
            'avatar' => null,
            'token' => 'access',
            'refreshToken' => 'refresh',
            'expiresIn' => 999,
            'user' => [
                'id' => 123,
                'name' => 'Matt Allan',
                'email' => 'matt@passport.test',
            ],
        ], $user);
    }
}
