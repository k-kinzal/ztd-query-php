<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\EvaluationUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(EvaluationUse::class)]
#[Small]
final class EvaluationUseTest extends TestCase
{
    /**
     * @return iterable<string, array{DeferredEvaluation, int}>
     */
    public static function providerOperands(): iterable
    {
        yield 'wildcard' => [new DeferredEvaluation([new Star()]), 1096];
        yield 'row width' => [new DeferredEvaluation([new DeferredProjection(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))]), 1241];
        yield 'earlier name' => [new DeferredEvaluation([new SelectExpression(new ColumnUse(new Name('missing'))), new DeferredProjection(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))]), 1054];
        yield 'row dependency' => [new DeferredEvaluation([new DeferredProjection(new Row([new ColumnUse(new Name('missing')), new NumberLiteral('2')]))]), 1054];
    }

    #[DataProvider('providerOperands')]
    public function testRaisePreparesRetainedItemsInOrder(DeferredEvaluation $statement, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode($code);
        (new EvaluationUse())->raise($statement, $session);
    }

    #[DataProvider('providerOperands')]
    public function testStatementPreparesThePublishedImmediateRequest(DeferredEvaluation $instruction, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), new \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation($instruction->items));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Evaluation::class, $statement);
        $session->text = 'No source text for DO preparation';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode($code);
        (new EvaluationUse())->statement($statement, $session);
    }

}
