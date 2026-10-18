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
    public function testPrepareAllowsWindowPreparationInDo(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'IGNORE NULLS'");
        $session->query('DO LEAD(1) IGNORE NULLS OVER ()');
    }

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
    public function testDatabaseAndTableTransitionsUseTheSameRetainedInstruction(): void
    {
        $table = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'));
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new SelectExpression(new ColumnUse(new Name('a')))], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($table));
        $statement = new DeferredEvaluation([new Star(), new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($query))]);
        $session = (new Instance())->connect();
        $observe = static function () use ($session, $statement): int {
            $session->text = 'unrelated invalid input';
            try {
                (new EvaluationUse())->raise($statement, $session);
            } catch (\MySqlMemory\Error\SqlError $error) {
                return $error->getCode();
            }
        };
        self::assertSame(1046, $observe());
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        self::assertSame(1146, $observe());
        $session->query('CREATE TABLE t(a INT)');
        self::assertSame(1096, $observe());
        $session->query('DROP TABLE t');
        self::assertSame(1146, $observe());
        self::assertSame('unrelated invalid input', $session->text);
    }
}
