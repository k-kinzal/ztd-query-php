<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Mutation\UpsertRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;

#[CoversClass(UpsertRule::class)]
#[Medium]
final class UpsertRuleTest extends TestCase
{
    public function testTailLowersAnEmptyTailAndAReturningOnlyTail(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('INSERT INTO t VALUES (1)')->statement;
        $returning = $semantics->analyze('INSERT INTO t VALUES (1) RETURNING a, b')->statement;

        self::assertInstanceOf(InsertRows::class, $bare);
        self::assertInstanceOf(InsertRows::class, $returning);
        self::assertSame([], $bare->upserts);
        self::assertSame([], $bare->returning);
        self::assertSame([], $returning->upserts);
        self::assertCount(2, $returning->returning);
        self::assertInstanceOf(ResultColumn::class, $returning->returning[1]);
    }

    public function testTailLowersTargetedDoUpdateAndDoNothingClausesInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO t VALUES (1) ON CONFLICT (a) WHERE a > 0 DO UPDATE SET b = 1 WHERE c = 2 ON CONFLICT (b COLLATE nocase DESC, c) DO NOTHING');

        self::assertInstanceOf(InsertRows::class, $operation->statement);
        self::assertCount(2, $operation->statement->upserts);
        self::assertSame([], $operation->statement->returning);
        $update = $operation->statement->upserts[0];
        $nothing = $operation->statement->upserts[1];
        self::assertNotNull($update->target);
        self::assertCount(1, $update->target->terms);
        self::assertNotNull($update->target->where);
        self::assertCount(1, $update->assignments);
        self::assertInstanceOf(Assignment::class, $update->assignments[0]);
        self::assertSame('b', $update->assignments[0]->column->value);
        self::assertNotNull($update->where);
        self::assertNotNull($nothing->target);
        self::assertCount(2, $nothing->target->terms);
        self::assertInstanceOf(Collate::class, $nothing->target->terms[0]->expression);
        self::assertSame(SortDirection::Descending, $nothing->target->terms[0]->direction);
        self::assertNull($nothing->target->where);
        self::assertSame([], $nothing->assignments);
        self::assertNull($nothing->where);
        self::assertSame('INSERT INTO t VALUES (1) ON CONFLICT (a) WHERE a > 0 DO UPDATE SET b = 1 WHERE c = 2 ON CONFLICT (b COLLATE nocase DESC, c) DO NOTHING', $operation->toString());
    }

    public function testTailLowersTheUntargetedClausesThatEndTheTailWithTheirReturning(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $nothing = $semantics->analyze('INSERT INTO t VALUES (1) ON CONFLICT DO NOTHING RETURNING a');
        $update = $semantics->analyze('INSERT INTO t VALUES (1) ON CONFLICT DO UPDATE SET a = 1 WHERE b');

        self::assertInstanceOf(InsertRows::class, $nothing->statement);
        self::assertInstanceOf(InsertRows::class, $update->statement);
        self::assertCount(1, $nothing->statement->upserts);
        self::assertNull($nothing->statement->upserts[0]->target);
        self::assertSame([], $nothing->statement->upserts[0]->assignments);
        self::assertCount(1, $nothing->statement->returning);
        self::assertCount(1, $update->statement->upserts);
        self::assertNull($update->statement->upserts[0]->target);
        self::assertCount(1, $update->statement->upserts[0]->assignments);
        self::assertNotNull($update->statement->upserts[0]->where);
        self::assertSame([], $update->statement->returning);
        self::assertSame('INSERT INTO t VALUES (1) ON CONFLICT DO NOTHING RETURNING a', $nothing->toString());
        self::assertSame('INSERT INTO t VALUES (1) ON CONFLICT DO UPDATE SET a = 1 WHERE b', $update->toString());
    }

    public function testTailKeepsATargetedClauseBeforeTheUntargetedLastOne(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO t VALUES (1) ON CONFLICT (a) DO NOTHING ON CONFLICT DO UPDATE SET c = excluded.c RETURNING *');

        self::assertInstanceOf(InsertRows::class, $operation->statement);
        self::assertCount(2, $operation->statement->upserts);
        self::assertNotNull($operation->statement->upserts[0]->target);
        self::assertNull($operation->statement->upserts[1]->target);
        self::assertCount(1, $operation->statement->returning);
        self::assertSame('INSERT INTO t VALUES (1) ON CONFLICT (a) DO NOTHING ON CONFLICT DO UPDATE SET c = excluded.c RETURNING *', $operation->toString());
    }
}
