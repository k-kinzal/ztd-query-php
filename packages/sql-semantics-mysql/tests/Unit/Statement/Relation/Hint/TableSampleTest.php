<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(TableSample::class)]
#[Medium]
final class TableSampleTest extends TestCase
{
    public function testRenderWritesTheClause(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-9.1.0');

        self::assertSame('SELECT a FROM t x TABLESAMPLE SYSTEM (@p)', $semantics->analyze('select a from t x tablesample system (@p)')->toString());
        self::assertSame('SELECT a FROM t TABLESAMPLE BERNOULLI (?)', $semantics->analyze('select a from t tablesample bernoulli (?)')->toString());
    }

    public function testThePercentageIsDerivedWithoutColumns(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('SELECT a FROM t TABLESAMPLE BERNOULLI (10)');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        self::assertNotNull($operation->statement->from->sample);
        self::assertInstanceOf(Known::class, $operation->facts->scalar($operation->statement->from->sample->percentage)->type);
    }

    public function testAnExpressionAsThePercentageIsRejected(): void
    {
        $this->expectExceptionMessage('A sampling percentage is a number, a user variable or a parameter marker.');

        new TableSample(SamplingMethod::System, new NullLiteral());
    }
}
