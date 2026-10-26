<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(\MySqlMemory\Command\Prepared\PreparedStatement::class)]
#[Medium]
final class PreparedIndexBoundaryTest extends TestCase
{
    public function testRunRetainsTheSameInstructionAndPreparationDatabaseAcrossStateChanges(): void
    {
        $definition = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d', 'other']))->connect(database: 'd');
        $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $definition);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
        $prepared = \MySqlMemory\Command\Prepared\PreparedStatement::capture($operation, $session);
        $session->variables->database = 'other';
        self::assertSame([], \MySqlMemory\Command\Prepared\ParameterBindings::capture($operation)->types);
        $before = $prepared->run($session, 'opaque request label', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $before[0]);
        self::assertSame(1146, $before[0]->getCode());
        $session->query('CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(7)');
        $after = $prepared->run($session, 'a different label', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $after[0]);
        self::assertSame(3800, $after[0]->getCode());
        self::assertSame($definition, $prepared->statement);
        self::assertSame('other', $session->variables->database);
        self::assertSame('a different label', $session->text);
    }
    public function testParametersExposesNoUnboundProtocolValues(): void
    {
        $definition = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $definition);
        self::assertSame([], \MySqlMemory\Command\Prepared\ParameterBindings::capture($operation)->types);
    }

    public function testParametersPreservesAMarkerInADeferredFunctionalOperand(): void
    {
        $definition = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Literal\Parameter())]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $definition);
        $prepared = \MySqlMemory\Command\Prepared\PreparedStatement::capture($operation, $session);
        self::assertCount(1, \MySqlMemory\Command\Prepared\ParameterBindings::capture($operation)->types);
        self::assertSame($definition, $prepared->statement);
    }

    public function testExecuteChecksTheRetainedPreparationDatabase(): void
    {
        $definition = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d', 'other']))->connect(database: 'other');
        $session->query('CREATE TABLE other.t(a INT)');
        $this->expectExceptionMessage("Table 'd.t' doesn't exist");
        $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $definition);
        $prepared = new \MySqlMemory\Command\Prepared\PreparedStatement($definition, $operation->profile(), M\Type\Resolved\Settings::of($operation->context), 'd');
        $prepared->execute($session, 'opaque request label', []);
    }

}
