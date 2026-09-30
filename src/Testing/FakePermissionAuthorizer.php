<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims\Testing;

use OurEdu\TokenClaims\PermissionAuthorizer;

/**
 * Allows only the given "resource.action" permissions, or everything with '*'.
 */
class FakePermissionAuthorizer extends PermissionAuthorizer
{
    /**
     * @param string[] $allowed
     */
    public function __construct(private readonly array $allowed)
    {
    }

    protected function ask(string $resource, string $action): bool
    {
        return in_array('*', $this->allowed, true)
            || in_array("$resource.$action", $this->allowed, true);
    }
}
