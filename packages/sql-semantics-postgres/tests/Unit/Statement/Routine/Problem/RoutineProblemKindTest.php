<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;

#[CoversClass(RoutineProblemKind::class)]
#[Small]
final class RoutineProblemKindTest extends TestCase
{
    public function testCasesCarryTheServerMessages(): void
    {
        self::assertSame('duplicate function body specified', RoutineProblemKind::DuplicateBody->value);
        self::assertSame('aggregate %s must be specified', RoutineProblemKind::MissingTransition->value);
    }
}
