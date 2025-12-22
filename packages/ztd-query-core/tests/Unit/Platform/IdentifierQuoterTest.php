<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Contract\IdentifierQuoterContractTest;
use Tests\Fake\FakeIdentifierQuoter;
use ZtdQuery\Platform\IdentifierQuoter;

#[CoversNothing]
final class IdentifierQuoterTest extends IdentifierQuoterContractTest
{
    protected function createQuoter(): IdentifierQuoter
    {
        return new FakeIdentifierQuoter();
    }

    protected function quoteCharacter(): string
    {
        return '"';
    }
}
