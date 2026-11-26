<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNamespace;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartition;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartitionRole;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;

#[CoversClass(IndexNamespace::class)]
#[Small]
final class IndexNamespaceTest extends TestCase
{
    public function testPartitionRoleUsesBothPartsOfTheDeclaredCoordinate(): void
    {
        $name = new Name('nodes');
        $first = new Name('tree');
        $second = new Name('children');
        $catalog = new IndexNamespace(partitionedTables: [
            new TablePartition($first, $name, TablePartitionRole::Branch),
            new TablePartition($second, $name, TablePartitionRole::Leaf),
        ]);

        self::assertSame(TablePartitionRole::Branch, $catalog->partitionRole($first, $name));
        self::assertSame(TablePartitionRole::Leaf, $catalog->partitionRole($second, $name));
        self::assertNull($catalog->partitionRole(new Name('another'), $name));
    }

    public function testPartitionedRecognizesEitherPositionInTheHierarchy(): void
    {
        $table = new TablePartition(new Name('tree'), new Name('child'), TablePartitionRole::Leaf);
        $catalog = new IndexNamespace(partitionedTables: [$table]);

        self::assertTrue($catalog->partitioned($table->schema, $table->table));
        self::assertFalse($catalog->partitioned($table->schema, new Name('independent')));
    }

    public function testKnownFindsExplicitAndStockSchemas(): void
    {
        $catalog = new IndexNamespace(schemas: [new Name('app')]);

        self::assertTrue($catalog->known(new Name('app')));
        self::assertTrue($catalog->known(new Name('pg_catalog')));
        self::assertFalse($catalog->known(new Name('absent')));
    }

    public function testHasUnknownNamesFindsExplicitAndInternalUncertainty(): void
    {
        $catalog = new IndexNamespace(opaque: [new Name('app')]);

        self::assertTrue($catalog->hasUnknownNames(new Name('app')));
        self::assertTrue($catalog->hasUnknownNames(new Name('pg_temp_19')));
        self::assertFalse($catalog->hasUnknownNames(new Name('other')));
    }
}
