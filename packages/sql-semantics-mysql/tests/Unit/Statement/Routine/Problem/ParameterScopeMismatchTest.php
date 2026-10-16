<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch;

#[CoversClass(ParameterScopeMismatch::class)]
#[Small]
final class ParameterScopeMismatchTest extends TestCase
{
    public function testMessageDescribesAClientScopeContradiction(): void
    {
        self::assertSame('A stored routine or trigger body has no client parameter binding scope.', (new ParameterScopeMismatch())->message());
    }
}
