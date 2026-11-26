<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation;

#[CoversClass(ForeignKeyStringStorage::class)]
#[Small]
final class ForeignKeyStringStorageTest extends TestCase
{
    public function testKeyBytesCountsNativeMaximumBytesPerCharacter(): void
    {
        $type = new ForeignKeyStringStorage(ForeignKeyStringKind::Varchar, 191, ForeignKeyTextCollation::Binary);

        self::assertSame(764, $type->keyBytes());
    }
}
