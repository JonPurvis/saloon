<?php

declare(strict_types=1);

namespace Saloon\Tests\Fixtures\Authenticators;

use DateTimeImmutable;
use Saloon\Http\Auth\AccessTokenAuthenticator;

class CustomOAuthAuthenticator extends AccessTokenAuthenticator
{
    /**
     * Constructor
     *
     * @param string $accessToken
     * @param string $greeting
     * @param string|null $refreshToken
     * @param \DateTimeImmutable|null $expiresAt
     */
    public function __construct(
        public readonly string             $accessToken,
        public readonly string             $greeting,
        public readonly ?string            $refreshToken = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
    ) {
        //
    }

    /**
     * @return string
     */
    public function getGreeting(): string
    {
        return $this->greeting;
    }
}
