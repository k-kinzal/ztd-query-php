<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;

#[CoversClass(ProgramKind::class)]
#[Small]
final class ProgramKindTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['PROCEDURE', 'FUNCTION', 'TRIGGER', 'EVENT'], array_map(static fn (ProgramKind $case): string => $case->value, ProgramKind::cases()));
    }
}
