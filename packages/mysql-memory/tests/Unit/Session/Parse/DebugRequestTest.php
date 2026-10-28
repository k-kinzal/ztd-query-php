<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\InternalCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Parse\DebugRequest;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics as D;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

#[CoversClass(DebugRequest::class)]
#[Medium]
final class DebugRequestTest extends TestCase
{
    public function testRefusalResolvesTheRoutineHeaderBeforeTheDebugBuildRestriction(): void
    {
        $session = (new Instance())->connect();
        $session->diagnostics->error(1327, 'Undeclared variable: previous');
        $statement = new ShowParseTree(new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new Block()));
        $session->text = 'unrelated input';
        $statement1 = (new Embedding())->statement($statement);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $actual = $result1;
        self::assertSame([1046, '3D000', 'No database selected'], $actual instanceof SqlError ? [$actual->getCode(), $actual->sqlState(), $actual->getMessage()] : null);
        self::assertSame([['Error', '1046', 'No database selected']], $warnings1);
    }

    public function testOperandFindsTheRetainedLocalTargetWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated input';
        $statement = new ShowParseTree(new D\GetDiagnostics(new D\StatementDiagnostics([new D\InformationItem(new Name('x'), D\StatementItemName::Number)])));
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context([], true, null, $session->resolution([])), $statement, new \SqlSemantics\Statement\Source\SourceMap());
        $problem = (new DebugRequest())->operand($operation);
        self::assertSame('Undeclared variable: x', $problem?->message());
        $statement2 = (new Embedding())->statement($statement);
        $parameters2 = array_fill(0, (new Bindings())->count($statement2), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation2 = (new Binding())->operation($session, $statement2, $parameters2, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
            $result2 = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2), $parameters2);
        } catch (SqlError $executionError) {
            $result2 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $actual = $result2;
        self::assertSame([1327, '42000', 'Undeclared variable: x'], $actual instanceof SqlError ? [$actual->getCode(), $actual->sqlState(), $actual->getMessage()] : null);
        self::assertSame('unrelated input', $session->text);
    }

    public function testPreservesThePreviousAreaForAnInspectedUserVariableTarget(): void
    {
        $session = (new Instance())->connect();
        $session->diagnostics->error(1327, 'Undeclared variable: previous');
        $statement = new ShowParseTree(new D\GetDiagnostics(new D\StatementDiagnostics([new D\InformationItem(new UserVariable(new Name('x')), D\StatementItemName::Number)])));
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context([], true, null, $session->resolution([])), $statement, new \SqlSemantics\Statement\Source\SourceMap());
        self::assertTrue((new DebugRequest())->preserves($operation));
        $error = (new DebugRequest())->refusal($operation, (new InternalCommand())->error($operation), $session);
        self::assertSame(1064, $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame([1327, 1064], array_column($session->diagnostics->conditions, 1));
    }

    public function testPreservesDoesNotHideAnInvalidConditionNumber(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated input';
        $statement = new ShowParseTree(new D\GetDiagnostics(new D\ConditionDiagnostics(new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date, 'bad'), [new D\InformationItem(new UserVariable(new Name('m')), \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MessageText)])));
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context([], true, null, $session->resolution([])), $statement);
        self::assertFalse((new DebugRequest())->preserves($operation));
        $error = (new DebugRequest())->refusal($operation, (new InternalCommand())->error($operation), $session);
        self::assertSame(1525, $error->getCode());
        self::assertSame("Incorrect DATE value: 'bad'", $error->getMessage());
        self::assertSame('unrelated input', $session->text);
    }

    /**
     * @return iterable<string, array{Scalar, list<int>}>
     */
    public static function providerOperands(): iterable
    {
        yield 'continuing parse error' => [new Collated(new NumberLiteral('1'), new Name('missing')), [1273, 1064]];
        yield 'aborting charset' => [new CharsetConversion(new NumberLiteral('1'), new CharsetName(new Name('missing'))), [1115]];
        yield 'later date interpretation' => [new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date, 'bad'), [1064]];
        yield 'later time interpretation' => [new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Time, 'bad'), [1064]];
        yield 'later timestamp interpretation' => [new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp, 'bad'), [1064]];
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
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context([], true, null, $session->resolution([])), new ShowParseTree(new QueryRoot(new Select([], [new SelectExpression($operand)]))), new \SqlSemantics\Statement\Source\SourceMap());
        $error = (new DebugRequest())->notices($operation->statementInformation()->warnings, (new InternalCommand())->error($operation), $session);

        self::assertSame($codes[0], $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusalLeavesContextualizationConditionsForTheirOwnStage(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $partitioning = new PartitionClause(new HashMethod(false, new Collated(new FunctionCall(new Name('ABS'), [new CallArgument(new NumberLiteral('1'))]), new Name('missing'))), null, null, []);
        $statement = new ShowParseTree(new CreateTable(new QualifiedName(new Name('t')), [new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)))], partitioning: $partitioning));
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context([], true, null, $session->resolution([])), $statement, new \SqlSemantics\Statement\Source\SourceMap());

        $error = (new DebugRequest())->refusal($operation, (new InternalCommand())->error($operation), $session);

        self::assertSame(1273, $error->getCode());
        self::assertSame([1273, 1064], array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusalPreservesTheColumnlessInputWithTheSameContextualCondition(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run('SHOW PARSE_TREE CREATE TABLE t PARTITION BY HASH(@v COLLATE missing)')[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1273, $error->getCode());
        self::assertSame([1273, 1064], array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusalPreservesTheDebugBoundaryBeforeAnAlterPartitionCondition(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run('SHOW PARSE_TREE ALTER TABLE t PARTITION BY HASH(USER())')[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1064, $error->getCode());
        self::assertStringStartsWith('You have an error in your SQL syntax;', $error->getMessage());
        self::assertSame([1064], array_column($session->diagnostics->conditions, 1));
    }
}
