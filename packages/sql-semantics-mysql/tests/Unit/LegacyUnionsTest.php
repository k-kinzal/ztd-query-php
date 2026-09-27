<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\LegacyUnions;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(LegacyUnions::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Queries::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Expressions::class)]
#[Medium]
final class LegacyUnionsTest extends TestCase
{
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-5.6.51'])]
    public function testUnionAllAppendsToTheChainOfTheFirstSelect(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2', Writer::render($rows));
        $three = $builder->unionAll($rows, $semantics->analyze('SELECT 3 FROM t')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3 FROM t', Writer::render($three));
        self::assertSame(Writer::render($three), $semantics->analyze(Writer::render($three))->toString());
        Composed::assertQueryRoundTrips($semantics, $three);
        $parens = $builder->unionAll($semantics->analyze('(SELECT 1)')->command, $semantics->analyze('SELECT 2')->command);
        self::assertSame('( SELECT 1 ) UNION ALL SELECT 2', Writer::render($parens));
        self::assertSame(Writer::render($parens), $semantics->analyze(Writer::render($parens))->toString());
    }

    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-5.6.51'])]
    public function testUnionAllParenthesizesASelectWithItsOwnOrderByOrLimit(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        $ordered = $builder->unionAll($semantics->analyze('SELECT 3 ORDER BY 1')->command, $semantics->analyze('SELECT 1')->command);
        self::assertSame('( SELECT 3 ORDER BY 1 ) UNION ALL SELECT 1', Writer::render($ordered));
        self::assertSame(Writer::render($ordered), $semantics->analyze(Writer::render($ordered))->toString());
        $limited = $builder->unionAll($semantics->analyze('SELECT 3 FROM t LIMIT 1')->command, $semantics->analyze('SELECT 1')->command);
        self::assertSame('( SELECT 3 FROM t LIMIT 1 ) UNION ALL SELECT 1', Writer::render($limited));
        $nested = $builder->unionAll($semantics->analyze('SELECT (SELECT 3 ORDER BY 1) FROM t')->command, $semantics->analyze('SELECT 1')->command);
        self::assertSame('SELECT( SELECT 3 ORDER BY 1 ) FROM t UNION ALL SELECT 1', Writer::render($nested));
    }

    public function testUnionAllRejectsAnOperandThatCannotTakeAUnionList(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $builder = $semantics->builder();
        $this->expectException(CompositionException::class);
        $builder->unionAll($semantics->analyze('SELECT 1')->command, $semantics->analyze('DELETE FROM t')->command);
    }
}
