<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims\Testing;

use OurEdu\TokenClaims\ClaimsFailure;
use OurEdu\TokenClaims\TokenClaims;
use OurEdu\TokenClaims\TokenClaimsResolver;

/**
 * Resolves to fixed claims, or a fixed failure, without calling IAM.
 */
class FakeTokenClaimsResolver extends TokenClaimsResolver
{
    public function __construct(
        private readonly ?TokenClaims $fakeClaims,
        private readonly ?ClaimsFailure $fakeFailure = null,
    ) {
    }

    protected function fetch(): ?TokenClaims
    {
        $this->failure = $this->fakeFailure;

        return $this->fakeClaims;
    }
}
