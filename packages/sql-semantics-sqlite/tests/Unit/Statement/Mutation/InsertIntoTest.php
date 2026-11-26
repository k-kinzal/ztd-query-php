<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(InsertInto::class)]
#[Medium]
final class InsertIntoTest extends TestCase
{
    public function testRenderWritesTheVerbTheResolutionTheTargetAndTheColumns(): void
    {
        $semantics = new Inspection();
        $replace = $semantics->inspect('replace into t (a, b) values (1, 2)');
        $insert = $semantics->inspect('insert or abort into main.t as x (a) values (1)');
        $with = $semantics->inspect('with w as (select 1 as q) insert into t select q from w');

        self::assertSame('REPLACE INTO t (a, b) VALUES (1, 2)', $replace->toString());
        self::assertInstanceOf(InsertRows::class, $replace->statement);
        self::assertTrue($replace->statement->into->replace);
        self::assertNull($replace->statement->into->resolution);
        self::assertSame(['a', 'b'], array_map(static fn (Name $name): string => $name->value, $replace->statement->into->columns));
        self::assertSame('INSERT OR ABORT INTO main.t AS x (a) VALUES (1)', $insert->toString());
        self::assertInstanceOf(InsertRows::class, $insert->statement);
        self::assertFalse($insert->statement->into->replace);
        self::assertSame(ConflictResolution::Abort, $insert->statement->into->resolution);
        self::assertSame('x', $insert->statement->into->target->alias?->value);
        self::assertSame('WITH w AS (SELECT 1 AS q) INSERT INTO t SELECT q FROM w', $with->toString());
        self::assertInstanceOf(InsertSelect::class, $with->statement);
        self::assertNotNull($with->statement->into->with);
        self::assertSame([], $with->statement->into->columns);
    }

    public function testRejectsReplaceWithAConflictResolution(): void
    {
        $this->expectExceptionMessage('REPLACE takes no conflict resolution.');

        new InsertInto(new MutationTarget(new QualifiedName(new Name('t'))), [], true, ConflictResolution::Ignore);
    }

    public function testRejectsATargetWithAnIndexChoice(): void
    {
        $this->expectExceptionMessage('The table of an INSERT takes no index choice.');

        new InsertInto(new MutationTarget(new QualifiedName(new Name('t')), null, new IndexChoice()));
    }
}
