<?php

declare(strict_types=1);

namespace MattAllan\PassportSocialite;

use Illuminate\Support\Arr;
use GuzzleHttp\ClientInterface;
use Laravel\Socialite\Two\User;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;

class PassportProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ' ';

    /**
     * @param  string $refreshToken
     * @return User
     */
    public function refresh(string $refreshToken): User
    {
        $response = $this->getRefreshTokenResponse($refreshToken);

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = Arr::get($response, 'access_token')
        ));

        return $user->setToken($token)
                    ->setRefreshToken(Arr::get($response, 'refresh_token'))
                    ->setExpiresIn(Arr::get($response, 'expires_in'));
    }

    protected function getRefreshTokenResponse(string $refreshToken): array
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getRefreshTokenFields($refreshToken),
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Get the POST fields for the refresh token request.
     *
     * @param  string  $refreshToken
     * @return array
     */
    protected function getRefreshTokenFields(string $refreshToken): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(config('services.passport.url').'/oauth/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return config('services.passport.url').'/oauth/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(config('services.passport.url').'/api/user', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => Arr::get($user, 'id'),
            'nickname' => Arr::get($user, 'email'),
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'avatar_url'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }
}
