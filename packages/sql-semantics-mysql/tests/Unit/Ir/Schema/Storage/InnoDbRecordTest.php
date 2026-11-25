<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\CharacterStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\Storage\InnoDbRecord;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;

#[CoversClass(InnoDbRecord::class)]
#[Small]
final class InnoDbRecordTest extends TestCase
{
    public function testFitsChecksTheHiddenClusterKeyAndNullableSlots(): void
    {
        $required = new TableColumn(new Identifier('value'), IntegerStorage::Wide, false);
        $nullable = new TableColumn(new Identifier('value'), IntegerStorage::Wide);

        self::assertTrue(InnoDbRecord::fits(array_fill(0, 1012, $required), false));
        self::assertFalse(InnoDbRecord::fits(array_fill(0, 1013, $required), false));
        self::assertTrue(InnoDbRecord::fits(array_fill(0, 1013, $required), true));
        self::assertTrue(InnoDbRecord::fits(array_fill(0, 997, $nullable), false));
        self::assertFalse(InnoDbRecord::fits(array_fill(0, 998, $nullable), false));
    }

    public function testFitsChecksTheCompactLengthOfADynamicVarcharField(): void
    {
        $field = new TableColumn(new Identifier('value'), new CharacterStorage(64));

        self::assertTrue(InnoDbRecord::fits(array_fill(0, 196, $field), false));
        self::assertFalse(InnoDbRecord::fits(array_fill(0, 197, $field), false));
    }
}
