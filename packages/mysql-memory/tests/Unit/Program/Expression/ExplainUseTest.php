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
}
