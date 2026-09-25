<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Storage\SetColumnForeignOptions::class)]
#[Medium]
final class SetColumnForeignOptionsTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind("ALTER FOREIGN TABLE t ALTER COLUMN id OPTIONS (SET column_name 'remote_c')", strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Storage\SetColumnForeignOptions::class, $statement->actions[0]);
        self::assertSame('id', $statement->actions[0]->column);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Foreign\SetForeignOption::class, $statement->actions[0]->changes[0]);
        self::assertSame('ALTER FOREIGN TABLE "t" ALTER COLUMN "id" OPTIONS(SET "column_name" \'remote_c\')', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
