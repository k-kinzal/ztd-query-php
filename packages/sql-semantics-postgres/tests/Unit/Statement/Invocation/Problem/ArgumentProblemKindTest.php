<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblemKind;

#[CoversClass(ArgumentProblemKind::class)]
#[Small]
final class ArgumentProblemKindTest extends TestCase
{
    public function testCasesCarryTheServerMessages(): void
    {
        self::assertSame('positional argument cannot follow named argument', ArgumentProblemKind::PositionalAfterNamed->value);
    }
}
