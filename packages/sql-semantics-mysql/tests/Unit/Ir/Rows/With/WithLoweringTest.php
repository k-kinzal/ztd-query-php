<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithDefinition;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithLowering;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(WithLowering::class)]
#[Medium]
final class WithLoweringTest extends TestCase
{
    public function testAnalyzeClosesQuotedDefinitionsOverOneMode(): void
    {
        $analyzer = new Semantics(mode: new Mode(ansiQuotes: true));
        $input = 'WITH "a" AS (SELECT 7 AS "n"), "b" AS (SELECT "n" AS "m" FROM "a") SELECT "m" AS "answer" FROM "b"';
        $release = $analyzer->profile()->grammar;
        $mode = ProjectionMode::AnsiQuotes;
        $column = new ResultColumn(new IntegerLiteral('7'), new Identifier('n'));
        $value = new WithDefinition(new Select([$column], release: $release, mode: $mode));
        $dependent = new WithDefinition(new WithRead(0, [new LocalResultColumn(0, new Identifier('m'))]));
        $result = new WithRead(1, [new LocalResultColumn(0, new Identifier('answer'))]);
        $direct = new WithProjection([$value, $dependent], $result, $release, $mode);

        self::assertEquals($direct, $analyzer->analyze($input));
        self::assertEquals([$direct], $analyzer->analyzeAll($input));
    }

    public function testLowerKeepsBothDeclarations(): void
    {
        $lookup = (new Inspection())->inspect('WITH a AS (SELECT 1 AS n), b AS (SELECT n FROM a) SELECT n FROM b');
        self::assertInstanceOf(QueryExpression::class, $lookup->statement);
        $query = (new WithLowering())->lower($lookup->binding(), $lookup->statement);

        self::assertCount(2, $query->definitions);
        self::assertInstanceOf(WithRead::class, $query->body);
        self::assertSame(1, $query->body->definition);
    }

    public function testQueryLowersAnIndependentSelection(): void
    {
        $lookup = (new Inspection())->inspect('SELECT 1 AS n');
        self::assertInstanceOf(ParsedSelect::class, $lookup->statement);
        $query = (new WithLowering())->query($lookup->binding(), $lookup->statement, []);

        self::assertInstanceOf(Select::class, $query);
    }

    public function testReadUsesTheResolvedHeadingPosition(): void
    {
        $lookup = (new Inspection())->inspect('WITH a AS (SELECT 1 AS u, 2 AS v) SELECT v FROM a');
        self::assertInstanceOf(QueryExpression::class, $lookup->statement);
        $query = (new WithLowering())->lower($lookup->binding(), $lookup->statement);

        self::assertInstanceOf(WithRead::class, $query->body);
        self::assertSame(1, $query->body->columns[0]->slot);
    }
}
