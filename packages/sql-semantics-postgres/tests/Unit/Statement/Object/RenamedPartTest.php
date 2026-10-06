<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart;

#[CoversClass(RenamedPart::class)]
#[Small]
final class RenamedPartTest extends TestCase
{
    public function testCasesSpellThePart(): void
    {
        self::assertSame(['COLUMN', 'CONSTRAINT', 'ATTRIBUTE'], [RenamedPart::Column->value, RenamedPart::Constraint->value, RenamedPart::Attribute->value]);
    }
}
