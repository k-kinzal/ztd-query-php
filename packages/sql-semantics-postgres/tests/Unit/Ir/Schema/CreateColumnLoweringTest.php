<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateColumnLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(CreateColumnLowering::class)]
#[Medium]
final class CreateColumnLoweringTest extends TestCase
{
    public function testLowerResolvesTheSignedNativeStorageKind(): void
    {
        $query = (new Inspection())->inspect('CREATE TABLE app.a (value SMALLINT DEFAULT 5)');
        self::assertInstanceOf(ParsedCreateTable::class, $query->statement);
        self::assertInstanceOf(ListedColumns::class, $query->statement->definition);
        $parsed = $query->statement->definition->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $parsed);

        $value = (new CreateColumnLowering())->lower($query->binding(), $parsed);
        self::assertSame(IntegerStorage::Small, $value->storage);
    }
}
