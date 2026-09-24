<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\ChangeTablespaceDatafileStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeTablespaceDatafileStatement::class)]
#[Medium]
final class ChangeTablespaceDatafileStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testWithOriginPreservesTheSizes(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("ALTER TABLESPACE ts CHANGE DATAFILE 'f' INITIAL_SIZE 1K, AUTOEXTEND_SIZE 2 MAX_SIZE 3");
        self::assertInstanceOf(ChangeTablespaceDatafileStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame([1024, 2, 3], [$copy->initialSize, $copy->autoextendSize, $copy->maxSize]);
    }

    public function testWithDatafileKeepsTheSizes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("ALTER TABLESPACE ts CHANGE DATAFILE 'f' MAX_SIZE 3");
        self::assertInstanceOf(ChangeTablespaceDatafileStatement::class, $statement);
        self::assertSame("ALTER TABLESPACE `ts` CHANGE DATAFILE 'g' MAX_SIZE = 3", $statement->withDatafile('g')->toString());
    }

    public function testWithSizesRequiresOneSize(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("ALTER TABLESPACE ts CHANGE DATAFILE 'f' MAX_SIZE 3");
        self::assertInstanceOf(ChangeTablespaceDatafileStatement::class, $statement);
        self::assertSame(9, $statement->withSizes(9, null, null)->initialSize);
        $this->expectException(InvalidStructure::class);
        $statement->withSizes(null, null, null);
    }
}
