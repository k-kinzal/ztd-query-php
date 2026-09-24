<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;

#[CoversClass(ProgramKind::class)]
final class ProgramKindTest extends TestCase
{
    public function testNamesEveryStoredProgramKind(): void
    {
        self::assertSame(['PROCEDURE', 'FUNCTION', 'TRIGGER', 'EVENT'], array_column(ProgramKind::cases(), 'value'));
    }
}
