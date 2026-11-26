<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind;

#[CoversClass(ForeignKeyStringKind::class)]
#[Small]
final class ForeignKeyStringKindTest extends TestCase
{
    public function testTextDistinguishesByteAndCharacterFields(): void
    {
        self::assertTrue(ForeignKeyStringKind::Varchar->text());
        self::assertFalse(ForeignKeyStringKind::Varbinary->text());
    }

    public function testFixedDistinguishesBinaryAndVarbinaryStorage(): void
    {
        self::assertTrue(ForeignKeyStringKind::Binary->fixed());
        self::assertFalse(ForeignKeyStringKind::Varbinary->fixed());
    }
}
