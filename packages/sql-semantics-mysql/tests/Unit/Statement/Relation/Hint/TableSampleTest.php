<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableSample::class)]
#[Medium]
final class TableSampleTest extends TestCase
{
    public function testRenderWritesTheClause(): void
    {
        $semantics = new Inspection('mysql-9.1.0');

        self::assertSame('SELECT a FROM t x TABLESAMPLE SYSTEM (@p)', $semantics->inspect('select a from t x tablesample system (@p)')->toString());
        self::assertSame('SELECT a FROM t TABLESAMPLE BERNOULLI (?)', $semantics->inspect('select a from t tablesample bernoulli (?)')->toString());
    }

    public function testThePercentageIsDerivedWithoutColumns(): void
    {
        $operation = (new Inspection('mysql-9.1.0'))->inspect('SELECT a FROM t TABLESAMPLE BERNOULLI (10)');

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
