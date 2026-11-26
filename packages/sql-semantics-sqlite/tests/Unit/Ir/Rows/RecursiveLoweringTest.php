<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveDefinition;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveLowering;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn as ParsedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(RecursiveLowering::class)]
#[Medium]
final class RecursiveLoweringTest extends TestCase
{
    public function testLowerBindsTheSuccessorAndConsumerToTheNamedSlot(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE main.walk (n)']);
        $query = $semantics->analyze(
            'WITH walk("N") AS (SELECT 0 UNION ALL SELECT c.n+1 FROM walk c WHERE c.N<3) SELECT walk.n AS answer FROM walk',
            $context,
        );

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(LocalSource::class, $query->source);
        self::assertInstanceOf(RecursiveDefinition::class, $query->source->query);
        self::assertInstanceOf(Arithmetic::class, $query->source->query->step[0]->expression);
        self::assertEquals(new ColumnReference(0), $query->source->query->step[0]->expression->left);
        self::assertEquals(new ColumnReference(0), $query->columns[0]->expression);
        self::assertStringContainsString('UNION ALL SELECT', $query->toString());
    }

    public function testAnchorNormalizesAnIndependentProjection(): void
    {
        $inspected = (new Inspection())->inspect('SELECT 7 AS number');
        self::assertInstanceOf(ParsedSelect::class, $inspected->statement);

        $anchor = (new RecursiveLowering())->anchor($inspected->binding(), $inspected->statement);

        self::assertEquals(new IntegerLiteral('7'), $anchor->rows[0]->values[0]);
    }

    public function testOperandsKeepTheProjectionOrder(): void
    {
        $inspected = (new Inspection())->inspect('SELECT 1 AS a, NULL AS b');
        self::assertInstanceOf(ParsedSelect::class, $inspected->statement);

        $operands = (new RecursiveLowering())->operands($inspected->statement);

        self::assertInstanceOf(ParsedColumn::class, $inspected->statement->columns[0]);
        self::assertInstanceOf(ParsedColumn::class, $inspected->statement->columns[1]);
        self::assertEquals($inspected->statement->columns[0]->expression, $operands[0]);
        self::assertEquals($inspected->statement->columns[1]->expression, $operands[1]);
    }

    public function testRejectsTheExternalHomonymAsTheRecursiveInput(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE main.walk (n)']);
        $this->expectExceptionMessage('The recursive input must resolve to this local definition.');

        $semantics->analyze('WITH walk(n) AS (VALUES (1) UNION SELECT n FROM main.walk) SELECT n FROM walk', $context);
    }
}
