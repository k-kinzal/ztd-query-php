<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression\Evaluation;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\Evaluation\Preparation;
use MySqlMemory\Program\Expression\EvaluationUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Preparation::class)]
#[Small]
final class PreparationTest extends TestCase
{
    public function testPrepareAllowsWindowPreparationInDo(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'IGNORE NULLS'");
        $session->query('DO LEAD(1) IGNORE NULLS OVER ()');
    }

    public function testCheckUsesThePublishedImmediateBindings(): void
    {
        $session = (new Instance())->connect();
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), new \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation([new Star()]));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Evaluation::class, $statement);
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1096);
        (new Preparation())->check($statement, $statement->statementInformation(), $session);
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
