<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\DiscardTarget::class)]
#[Small]
final class DiscardTargetTest extends TestCase
{
    public function testTemporaryHoldsForBothSpellingsOnly(): void
    {
        self::assertSame([true, true, false], [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\DiscardTarget::Temp->temporary(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\DiscardTarget::Temporary->temporary(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\DiscardTarget::Plans->temporary()]);
    }
}
