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
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(Resolution::class)]
#[Medium]
final class ResolutionTest extends TestCase
{
    public function testTheFiveOutcomesAreTheOnlyResolutions(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $declarations = [$semantics->analyze('CREATE TABLE t (a INTEGER, b INTEGER)'), $semantics->analyze('CREATE TABLE u (a INTEGER)')];
        $query = $semantics->analyze('SELECT t.b AS x, a, c FROM t, u ORDER BY x', $declarations);
        $statement = $query->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResolvedColumn::class, $query->field('x')->resolution);
        self::assertInstanceOf(AmbiguousColumn::class, $query->field('a')->resolution);
        self::assertInstanceOf(MissingColumn::class, $query->field('c')->resolution);
        self::assertInstanceOf(AliasTarget::class, $query->facts->scalar($statement->orderBy[0]->expression)->resolution);
        self::assertInstanceOf(ConditionalColumn::class, $semantics->analyze('SELECT a FROM t')->field('a')->resolution);
    }
}
