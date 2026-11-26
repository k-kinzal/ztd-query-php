<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(WithLowering::class)]
#[Medium]
final class WithLoweringTest extends TestCase
{
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
        $body = $lookup->statement->body;
        self::assertInstanceOf(ParsedSelect::class, $body);
        self::assertInstanceOf(TableInput::class, $body->from);
        $read = (new WithLowering())->read($lookup->binding(), $body, $body->from, 0);

        self::assertSame(1, $read->columns[0]->slot);
    }
}
