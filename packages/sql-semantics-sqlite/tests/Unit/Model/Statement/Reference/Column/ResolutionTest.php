<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\Resolution;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Resolution::class)]
#[Medium]
final class ResolutionTest extends TestCase
{
    public function testTheFiveOutcomesAreTheOnlyResolutions(): void
    {
        $semantics = new Inspection();
        $declarations = [$semantics->diagnose('CREATE TABLE t (a INTEGER, b INTEGER)'), $semantics->diagnose('CREATE TABLE u (a INTEGER)')];
        $query = $semantics->diagnose('SELECT t.b AS x, a, c FROM t, u ORDER BY x', $declarations);
        $statement = $query->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResolvedColumn::class, $query->field('x')->resolution);
        self::assertInstanceOf(AmbiguousColumn::class, $query->field('a')->resolution);
        self::assertInstanceOf(MissingColumn::class, $query->field('c')->resolution);
        self::assertInstanceOf(AliasTarget::class, $query->facts->scalar($statement->orderBy[0]->expression)->resolution);
        self::assertInstanceOf(ConditionalColumn::class, $semantics->diagnose('SELECT a FROM t')->field('a')->resolution);
    }
}
