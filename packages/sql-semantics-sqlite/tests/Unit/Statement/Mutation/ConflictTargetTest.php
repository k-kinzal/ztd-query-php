<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ConflictTarget::class)]
#[Medium]
final class ConflictTargetTest extends TestCase
{
    public function testRenderWritesTheTermsAndThePredicate(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('insert into t values (1) on conflict (id collate nocase asc, a) where id > 0 do nothing');

        self::assertSame('INSERT INTO t VALUES (1) ON CONFLICT (id COLLATE nocase ASC, a) WHERE id > 0 DO NOTHING', $query->toString());
        self::assertInstanceOf(InsertRows::class, $query->statement);
        self::assertNotNull($query->statement->upserts[0]->target);
        self::assertCount(2, $query->statement->upserts[0]->target->terms);
        self::assertNotNull($query->statement->upserts[0]->target->where);
    }

    public function testTermsAndPredicateSeeTheTargetTableOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('INSERT INTO t VALUES (1, 2, 3) ON CONFLICT (id) WHERE excluded.id > 0 DO NOTHING', [$t]);

        self::assertInstanceOf(InsertRows::class, $query->statement);
        $target = $query->statement->upserts[0]->target;
        self::assertNotNull($target);
        $term = $query->facts->scalar($target->terms[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $term);
        self::assertSame($query->statement->into->target, $term->relation);
        self::assertSame($t->declarations()[0]->columns[0], $term->declaration());
        self::assertInstanceOf(Binary::class, $target->where);
        self::assertInstanceOf(MissingColumn::class, $query->facts->scalar($target->where->left)->resolution);
        self::assertInstanceOf(MissingColumn::class, $query->facts->diagnostics[0]);
        self::assertSame('excluded', $query->facts->diagnostics[0]->qualifier?->name->value);
    }

    public function testRejectsAnEmptyTermList(): void
    {
        $this->expectExceptionMessage('A conflict target names at least one indexed column.');

        new ConflictTarget([]);
    }
}
