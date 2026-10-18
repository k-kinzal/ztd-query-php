<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\ExplainUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ExplainUse::class)]
#[Medium]
final class ExplainUseTest extends TestCase
{
    public function testErrorConsumesTheSameStructureAcrossTableAndColumnTransitions(): void
    {
        $table = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
        $column = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('id'));
        $query = new QueryRoot(new Select([], [new SelectExpression($column)], $table));
        $instruction = new DeferredExplain($query, new Name('JSON'), new UserVariable(new Name('')));
        $session = (new Instance('8.1.0', [], ['d']))->connect(database: 'd');
        self::assertSame(1146, (new ExplainUse())->error($instruction, $session)->getCode());
        $session->query('CREATE TABLE t(x INT)');
        self::assertSame(1054, (new ExplainUse())->error($instruction, $session)->getCode());
        $session->query('ALTER TABLE t ADD COLUMN id INT');
        self::assertSame(3061, (new ExplainUse())->error($instruction, $session)->getCode());
        self::assertSame($query, $instruction->statement);
    }

    public function testRaiseRejectsTheTargetWithoutAssigningOrReturningAPlan(): void
    {
        $instruction = new DeferredExplain(new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))])), new Name('JSON'), new UserVariable(new Name('')));
        $session = (new Instance('8.1.0'))->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(3061);
        (new ExplainUse())->raise($instruction, $session);
    }
    public function testErrorKeepsTheDatabaseAndDeferredWriteAcrossStateTransitions(): void
    {
        $target = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
        $head = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, $target);
        $write = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery($head, new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()]));
        $instruction = new DeferredExplain($write, database: new Name('d'));
        $session = (new Instance('8.4.7', [], ['other']))->connect(database: 'other');
        $session->text = 'unrelated invalid input';
        $observe = static function () use ($instruction, $session): int {
            $session->text = 'unrelated invalid input';
            try {
                return (new ExplainUse())->error($instruction, $session)->getCode();
            } catch (\MySqlMemory\Error\SqlError $error) {
                return $error->getCode();
            }
        };
        self::assertSame(1049, $observe());
        $session->query('CREATE DATABASE d');
        self::assertSame(1146, $observe());
        $session->query('CREATE TABLE d.t(a INT)');
        $session->query('INSERT INTO d.t VALUES(7)');
        self::assertSame(1096, $observe());
        self::assertSame('other', $session->variables->database);
        self::assertSame('unrelated invalid input', $session->text);
        self::assertSame($write, $instruction->statement);
        $rows = $session->query('SELECT a FROM d.t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['7']], $rows->rows);
    }
}
