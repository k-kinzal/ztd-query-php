<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateColumnLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateColumnLowering::class)]
#[Medium]
final class CreateColumnLoweringTest extends TestCase
{
    public function testLowerResolvesTheSignedNativeStorageKind(): void
    {
        $query = (new Inspection())->inspect('CREATE TABLE app.a (value SMALLINT DEFAULT 5)');
        self::assertInstanceOf(ParsedCreateTable::class, $query->statement);
        $parsed = $query->statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $parsed);

        $value = (new CreateColumnLowering())->lower($query->binding(), $parsed);
        self::assertSame(IntegerStorage::Small, $value->storage);
    }
}
