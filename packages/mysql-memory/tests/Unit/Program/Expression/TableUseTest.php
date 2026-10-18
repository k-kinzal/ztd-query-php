<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\TableUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TableUse::class)]
#[Medium]
final class TableUseTest extends TestCase
{
    public function testRaiseUsesTheRetainedTargetWithoutInputText(): void
    {
        $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), options: [new M\Table\Option\UnionOption([])], query: new M\Table\TableQuery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))])));
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated input';
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("'d.t' is not BASE TABLE");
        (new TableUse())->raise($definition, $session);
    }

    public function testHeaderReportsUnknownEnginesBeforeTheQueryRole(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1286);
        $session->query('CREATE TABLE t ENGINE=missing UNION=() SELECT ROW(1,2)');
    }

    public function testPrepareResolvesTheSourceBeforeRetainingTheDefinition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $session->query("PREPARE st FROM 'CREATE TABLE t UNION=() SELECT * FROM missing'");
    }
}
