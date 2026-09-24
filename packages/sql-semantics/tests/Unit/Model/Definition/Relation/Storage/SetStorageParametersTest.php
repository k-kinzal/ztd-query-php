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

#[CoversClass(Relation\Storage\SetStorageParameters::class)]
#[Medium]
final class SetStorageParametersTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET (fillfactor = 70, toast.autovacuum_enabled = false)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Storage\SetStorageParameters::class, $statement->actions[0]);
        self::assertSame(['toast', 'autovacuum_enabled'], $statement->actions[0]->parameters[1]->name->parts);
        self::assertSame('ALTER TABLE "t" SET ("fillfactor" = 70, "toast"."autovacuum_enabled" = false)', $statement->toString());
    }
}
