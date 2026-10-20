<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(\MySqlMemory\Command\Prepared\PendingIndex::class)]
#[Medium]
final class PendingIndexTest extends TestCase
{
    public function testRunRetainsTheSameInstructionAndPreparationDatabaseAcrossStateChanges(): void
    {
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $prepared = new \MySqlMemory\Command\Prepared\PendingIndex($definition, 'd');
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d', 'other']))->connect(database: 'other');
        self::assertSame([], $prepared->parameters()->types);
        $before = $prepared->run($session, 'opaque request label', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $before[0]);
        self::assertSame(1146, $before[0]->getCode());
        $session->query('CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(7)');
        $after = $prepared->run($session, 'a different label', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $after[0]);
        self::assertSame(3800, $after[0]->getCode());
        self::assertSame($definition, $prepared->definition);
        self::assertSame('other', $session->variables->database);
        self::assertSame('a different label', $session->text);
    }
    public function testParametersExposesNoUnboundProtocolValues(): void
    {
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        self::assertSame([], (new \MySqlMemory\Command\Prepared\PendingIndex($definition, 'd'))->parameters()->types);
    }

    public function testExecuteChecksTheRetainedPreparationDatabase(): void
    {
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d', 'other']))->connect(database: 'other');
        $session->query('CREATE TABLE other.t(a INT)');
        $this->expectExceptionMessage("Table 'd.t' doesn't exist");
        (new \MySqlMemory\Command\Prepared\PendingIndex($definition, 'd'))->execute($session, 'opaque request label', []);
    }

}
