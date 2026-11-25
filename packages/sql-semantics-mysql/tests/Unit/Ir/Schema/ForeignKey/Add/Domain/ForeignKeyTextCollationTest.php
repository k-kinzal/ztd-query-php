<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation;

#[CoversClass(ForeignKeyTextCollation::class)]
#[Small]
final class ForeignKeyTextCollationTest extends TestCase
{
    public function testUnicodeKeepsItsNativeLabel(): void
    {
        self::assertSame('utf8mb4_unicode_ci', ForeignKeyTextCollation::Unicode->value);
    }
}
