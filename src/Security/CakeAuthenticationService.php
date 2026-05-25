<?php
declare(strict_types=1);

namespace CakeGraphQL\Security;

use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;

final class CakeAuthenticationService implements AuthenticationServiceInterface
{
    private ?object $identity = null;

    public function setIdentity(?object $identity): void
    {
        $this->identity = $identity;
    }

    public function clearIdentity(): void
    {
        $this->identity = null;
    }

    public function getIdentity(): ?object
    {
        return $this->identity;
    }

    public function isLogged(): bool
    {
        return $this->identity !== null;
    }

    public function getUser(): ?object
    {
        if ($this->identity === null) {
            return null;
        }

        if (method_exists($this->identity, 'getOriginalData')) {
            $user = $this->identity->getOriginalData();

            return is_object($user) ? $user : $this->identity;
        }

        return $this->identity;
    }
}
