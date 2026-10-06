<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(MutationTarget::class)]
#[Medium]
final class MutationTargetTest extends TestCase
{
    public function testNameAnswersTheWrittenTableName(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('UPDATE main.t AS x INDEXED BY i SET a = 1');

        self::assertInstanceOf(Update::class, $query->statement);
        self::assertSame('t', $query->statement->target->name()->name->value);
        self::assertSame('main', $query->statement->target->name()->schema?->value);
        self::assertSame('i', $query->statement->target->index?->index?->value);
    }

    public function testAliasAnswersTheCorrelationNameOrNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $aliased = $semantics->analyze('UPDATE main.t AS x SET a = 1');
        $plain = $semantics->analyze('UPDATE t SET a = 1');

        self::assertInstanceOf(Update::class, $aliased->statement);
        self::assertInstanceOf(Update::class, $plain->statement);
        self::assertSame('x', $aliased->statement->target->alias()?->value);
        self::assertNull($plain->statement->target->alias());
    }

    public function testDeriveRelationResolvesAmongDeclaredTablesOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $shadowed = $semantics->analyze('WITH t AS (SELECT 1 AS q) UPDATE t SET a = 1', [$t]);
        $common = $semantics->analyze('WITH w AS (SELECT 1 AS q) UPDATE w SET q = 1', [$t]);
        $undeclared = $semantics->analyze('UPDATE u SET a = 1', [$t], );

        self::assertInstanceOf(Update::class, $shadowed->statement);
        $fact = $shadowed->facts->relation($shadowed->statement->target);
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($t->declarations()[0], $fact->table->table);
        self::assertCount(3, $fact->shape->slots);
        self::assertSame([], $shadowed->facts->diagnostics);
        self::assertInstanceOf(Update::class, $common->statement);
        self::assertInstanceOf(MissingTable::class, $common->facts->relation($common->statement->target)->table);
        self::assertInstanceOf(Update::class, $undeclared->statement);
        self::assertInstanceOf(MissingTable::class, $undeclared->facts->relation($undeclared->statement->target)->table);
    }

    public function testDeriveRelationLeavesAnUndeclaredTargetOpen(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('DELETE FROM t RETURNING *');

        self::assertInstanceOf(Delete::class, $query->statement);
        $fact = $query->facts->relation($query->statement->target);
        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertFalse($fact->shape->complete());
        self::assertNull($query->fields());
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testRenderWritesSchemaAliasAndIndexChoice(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $built = new Operation($semantics->context(), new Delete(new MutationTarget(new QualifiedName(new Name('t'), new Name('main')), new Name('x'), new IndexChoice(new Name('i')))));

        self::assertSame('DELETE FROM main.t AS x INDEXED BY i', $built->toString());
        self::assertSame('UPDATE t AS x NOT INDEXED SET a = 1', $semantics->analyze('update t as x not indexed set a = 1')->toString());
    }
}
