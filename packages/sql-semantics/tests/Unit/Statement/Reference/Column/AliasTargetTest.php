<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Reference\Column\AliasTarget;

#[CoversClass(AliasTarget::class)]
#[Medium]
final class AliasTargetTest extends TestCase
{
    public function testFieldIsTheOutputFieldTheAliasNames(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS x, 2 AS y ORDER BY y');
        $statement = $query->statement;

        self::assertInstanceOf(Select::class, $statement);
        $resolution = $query->facts->scalar($statement->orderBy[0]->expression)->resolution;
        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame($query->field('y'), $resolution->field);
        self::assertSame(1, $resolution->field->position);
        self::assertSame(0, $resolution->depth);
        self::assertSame(2, (new AliasTarget($query->field('y'), 2))->depth);
    }

    public function testFieldIsNotUsedWhenTheNameIsAColumnOfTheInput(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER, x INTEGER)');
        $query = $semantics->analyze('SELECT a AS x FROM t ORDER BY x', [$table]);
        $statement = $query->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(AliasTarget::class, $query->facts->scalar($statement->orderBy[0]->expression)->resolution);
    }
}
