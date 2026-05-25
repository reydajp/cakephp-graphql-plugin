<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Security;

use CakeGraphQL\Security\CakeAuthenticationService;
use PHPUnit\Framework\TestCase;

final class CakeAuthenticationServiceTest extends TestCase
{
    public function testStartsWithoutLoggedIdentity(): void
    {
        $service = new CakeAuthenticationService();

        $this->assertFalse($service->isLogged());
        $this->assertNull($service->getIdentity());
        $this->assertNull($service->getUser());
    }

    public function testReturnsOriginalUserDataWhenIdentityProvidesIt(): void
    {
        $user = new \stdClass();
        $identity = new class ($user) {
            public function __construct(private readonly object $user)
            {
            }

            public function getOriginalData(): object
            {
                return $this->user;
            }
        };
        $service = new CakeAuthenticationService();

        $service->setIdentity($identity);

        $this->assertTrue($service->isLogged());
        $this->assertSame($identity, $service->getIdentity());
        $this->assertSame($user, $service->getUser());
    }

    public function testReturnsIdentityWhenNoOriginalUserDataIsAvailable(): void
    {
        $identity = new \stdClass();
        $service = new CakeAuthenticationService();

        $service->setIdentity($identity);

        $this->assertTrue($service->isLogged());
        $this->assertSame($identity, $service->getIdentity());
        $this->assertSame($identity, $service->getUser());
    }

    public function testClearIdentityResetsCurrentUser(): void
    {
        $service = new CakeAuthenticationService();
        $service->setIdentity(new \stdClass());

        $service->clearIdentity();

        $this->assertFalse($service->isLogged());
        $this->assertNull($service->getIdentity());
        $this->assertNull($service->getUser());
    }
}
