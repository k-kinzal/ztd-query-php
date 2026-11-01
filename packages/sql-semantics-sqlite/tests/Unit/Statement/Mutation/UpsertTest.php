<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(Upsert::class)]
#[Medium]
final class UpsertTest extends TestCase
{
    public function testRenderWritesDoNothingOrDoUpdateWithItsPredicate(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $nothing = $semantics->analyze('insert into t values (1) on conflict do nothing');
        $update = $semantics->analyze('insert into t values (1) on conflict (id) do update set a = 1, (b, a) = (2, 3) where a > 0');

        self::assertSame('INSERT INTO t VALUES (1) ON CONFLICT DO NOTHING', $nothing->toString());
        self::assertInstanceOf(InsertRows::class, $nothing);
        self::assertNull($nothing->upserts[0]->target);
        self::assertSame([], $nothing->upserts[0]->assignments);
        self::assertNull($nothing->upserts[0]->where);
        self::assertSame('INSERT INTO t VALUES (1) ON CONFLICT (id) DO UPDATE SET a = 1, (b, a) = (2, 3) WHERE a > 0', $update->toString());
        self::assertInstanceOf(InsertRows::class, $update);
        self::assertNotNull($update->upserts[0]->target);
        self::assertCount(2, $update->upserts[0]->assignments);
        self::assertNotNull($update->upserts[0]->where);
    }

    public function testAssignmentsAndPredicateSeeTheTableAndExcluded(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('INSERT INTO t VALUES (1, 2, 3) ON CONFLICT (id) DO UPDATE SET a = excluded.a WHERE b > excluded.b', [$t]);

        self::assertInstanceOf(InsertRows::class, $query);
        $upsert = $query->upserts[0];
        self::assertInstanceOf(Assignment::class, $upsert->assignments[0]);
        self::assertInstanceOf(Binary::class, $upsert->where);
        $assigned = $upsert->assignments[0]->value->scalarInformation()->resolution;
        $kept = $upsert->where->left->scalarInformation()->resolution;
        $excluded = $upsert->where->right->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $assigned);
        self::assertInstanceOf(ResolvedColumn::class, $kept);
        self::assertInstanceOf(ResolvedColumn::class, $excluded);
        self::assertSame($t->declarations()[0]->columns[1], $assigned->declaration());
        self::assertSame($t->declarations()[0]->columns[2], $kept->declaration());
        self::assertSame($t->declarations()[0]->columns[2], $excluded->declaration());
        self::assertSame($query->into->target, $excluded->relation);
        self::assertSame([], $query->diagnostics());
    }

    public function testRejectsDoNothingWithAPredicate(): void
    {
        $this->expectExceptionMessage('DO NOTHING takes no predicate.');

        new Upsert(null, [], new IntegerLiteral('1'));
    }
}
