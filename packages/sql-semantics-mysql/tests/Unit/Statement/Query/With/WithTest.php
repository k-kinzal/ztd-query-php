<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(With::class)]
#[Medium]
final class WithTest extends TestCase
{
    public function testBindSeesEarlierTablesAndTheTableItself(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('WITH RECURSIVE a AS (SELECT 1 AS n), b AS (SELECT n FROM a UNION ALL SELECT n + 1 FROM b WHERE n < 3) SELECT n FROM b', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(Known::class, $operation->field('n')->type);
        self::assertSame(Nullability::Nullable, $operation->field('n')->nullability);
    }

    public function testBindReportsATableDefinedTwice(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH a AS (SELECT 1), A AS (SELECT 2) SELECT 3');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::DuplicateCommonTable, $operation->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesTheKeywordsAndTheTables(): void
    {
        self::assertSame('WITH RECURSIVE a AS (SELECT 1), b AS (SELECT 2) SELECT 3', (new Semantics(Dialect::MySql))->analyze('with recursive a as (select 1), b as (select 2) select 3')->toString());
    }

    public function testAClauseWithoutTablesIsRejected(): void
    {
        $this->expectExceptionMessage('A WITH clause defines at least one common table expression.');

        new With(true, []);
    }
}
