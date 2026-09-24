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

#[CoversClass(Relation\Storage\SetColumnOptions::class)]
#[Medium]
final class SetColumnOptionsTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET (n_distinct = 100)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Storage\SetColumnOptions::class, $statement->actions[0]);
        self::assertSame(['n_distinct'], $statement->actions[0]->parameters[0]->name->parts);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET ("n_distinct" = 100)', $statement->toString());
    }
}
