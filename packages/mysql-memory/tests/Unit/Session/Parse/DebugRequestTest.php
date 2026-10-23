<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Command\InternalCommand;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\DebugRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics as D;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

#[CoversClass(DebugRequest::class)]
#[Medium]
final class DebugRequestTest extends TestCase
{
    public function testRefusalResolvesTheRoutineHeaderBeforeTheDebugBuildRestriction(): void
    {
        $session = (new Instance())->connect();
        $session->diagnostics->error(1327, 'Undeclared variable: previous');
        $statement = new ShowParseTree(new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block()));
        $session->text = 'unrelated input';
        $actual = (new \Tests\Fake\MemoryClient())->observe($session, $statement);
        self::assertSame([1046, '3D000', 'No database selected'], $actual['error'] ?? null);
        self::assertSame([['Error', '1046', 'No database selected']], $actual['warnings']);
    }

    public function testOperandFindsTheRetainedLocalTargetWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated input';
        $statement = new ShowParseTree(new D\GetDiagnostics(new D\StatementDiagnostics([new D\InformationItem(new Name('x'), D\StatementItemName::Number)])));
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), $statement);
        $problem = (new DebugRequest())->operand($operation);
        self::assertSame('Undeclared variable: x', $problem?->message());
        $actual = (new \Tests\Fake\MemoryClient())->observe($session, $statement);
        self::assertSame([1327, '42000', 'Undeclared variable: x'], $actual['error'] ?? null);
        self::assertSame('unrelated input', $session->text);
    }

    public function testPreservesThePreviousAreaForAnInspectedUserVariableTarget(): void
    {
        $session = (new Instance())->connect();
        $session->diagnostics->error(1327, 'Undeclared variable: previous');
        $statement = new ShowParseTree(new D\GetDiagnostics(new D\StatementDiagnostics([new D\InformationItem(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name('x')), D\StatementItemName::Number)])));
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), $statement);
        self::assertTrue((new DebugRequest())->preserves($operation));
        $error = (new DebugRequest())->refusal($operation, (new InternalCommand())->error($operation), $session);
        self::assertSame(1064, $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame([1327, 1064], array_column($session->diagnostics->conditions, 1));
    }

    /**
     * @return iterable<string, array{Scalar, list<int>}>
     */
    public static function providerOperands(): iterable
    {
        yield 'continuing parse error' => [new Collated(new NumberLiteral('1'), new Name('missing')), [1273, 1064]];
        yield 'aborting charset' => [new CharsetConversion(new NumberLiteral('1'), new CharsetName(new Name('missing'))), [1115]];
        yield 'later binding diagnostic' => [new ColumnUse(new Name('absent')), [1064]];
    }

    /**
     * @param list<int> $codes The ordered native conditions
     */
    #[DataProvider('providerOperands')]
    public function testNoticesUsesTypedNoticesBeforeTheDebugBoundary(Scalar $operand, array $codes): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), new ShowParseTree(new QueryRoot(new Select([], [new SelectExpression($operand)]))));
        $error = (new DebugRequest())->notices($operation->facts->warnings, (new InternalCommand())->error($operation), $session);

        self::assertSame($codes[0], $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusalLeavesContextualizationConditionsForTheirOwnStage(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $partitioning = new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause(new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(false, new Collated(new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('ABS'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new NumberLiteral('1'))]), new Name('missing'))), null, null, []);
        $statement = new ShowParseTree(new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)))], partitioning: $partitioning));
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), $statement);

        $error = (new DebugRequest())->refusal($operation, (new InternalCommand())->error($operation), $session);

        self::assertSame(1273, $error->getCode());
        self::assertSame([1273, 1064], array_column($session->diagnostics->conditions, 1));
    }
    public function testRefusalPreservesTheColumnlessInputWithTheSameContextualCondition(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run('SHOW PARSE_TREE CREATE TABLE t PARTITION BY HASH(@v COLLATE missing)')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $error);
        self::assertSame(1273, $error->getCode());
        self::assertSame([1273, 1064], array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusalPreservesTheDebugBoundaryBeforeAnAlterPartitionCondition(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run('SHOW PARSE_TREE ALTER TABLE t PARTITION BY HASH(USER())')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $error);
        self::assertSame(1064, $error->getCode());
        self::assertStringStartsWith('You have an error in your SQL syntax;', $error->getMessage());
        self::assertSame([1064], array_column($session->diagnostics->conditions, 1));
    }

}
