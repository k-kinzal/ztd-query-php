<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\SetRowSecurity::class)]
#[Medium]
final class SetRowSecurityTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t FORCE ROW LEVEL SECURITY', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\SetRowSecurity(Relation\RowSecurityChange::Force), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" FORCE ROW LEVEL SECURITY', $statement->toString());
    }
}
