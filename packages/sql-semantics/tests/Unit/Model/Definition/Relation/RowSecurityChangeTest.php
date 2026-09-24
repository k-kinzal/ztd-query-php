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

#[CoversClass(Relation\RowSecurityChange::class)]
#[Medium]
final class RowSecurityChangeTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['ENABLE ROW LEVEL SECURITY', 'DISABLE ROW LEVEL SECURITY', 'FORCE ROW LEVEL SECURITY', 'NO FORCE ROW LEVEL SECURITY'], array_column(Relation\RowSecurityChange::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ENABLE ROW LEVEL SECURITY, NO FORCE ROW LEVEL SECURITY', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals([new Relation\SetRowSecurity(Relation\RowSecurityChange::Enable), new Relation\SetRowSecurity(Relation\RowSecurityChange::NoForce)], $statement->actions);
        self::assertSame('ALTER TABLE "t" ENABLE ROW LEVEL SECURITY, NO FORCE ROW LEVEL SECURITY', $statement->toString());
    }
}
