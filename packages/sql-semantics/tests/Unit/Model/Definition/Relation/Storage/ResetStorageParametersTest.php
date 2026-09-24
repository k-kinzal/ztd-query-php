<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Storage\ResetStorageParameters::class)]
#[Medium]
final class ResetStorageParametersTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t RESET (fillfactor, toast.autovacuum_enabled)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Storage\ResetStorageParameters([new QualifiedName(['fillfactor']), new QualifiedName(['toast', 'autovacuum_enabled'])]), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" RESET("fillfactor", "toast"."autovacuum_enabled")', $statement->toString());
    }
}
