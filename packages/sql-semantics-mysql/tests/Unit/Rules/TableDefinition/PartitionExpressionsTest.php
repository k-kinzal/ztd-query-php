<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Statement\Scalar;

#[CoversClass(PartitionExpressions::class)]
#[Small]
final class PartitionExpressionsTest extends TestCase
{
    public function testReleasesRetainsTheStructuralRoleIndependentlyOfExecutionRestrictions(): void
    {
        $json = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('JSON_VALID'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        $latitude = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('ST_LATITUDE'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        $query = new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($json)]));
        $impossible = new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($latitude)]));
        self::assertSame(3, (new PartitionExpressions())->releases($query));
        self::assertSame(511, (new PartitionExpressions())->releases($latitude));
        self::assertSame(3, (new PartitionExpressions())->releases($impossible));
    }

    public function testConditionDistinguishesTheRetainedPartitionRoles(): void
    {
        $one = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1');
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([$one, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $session = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('v')), $row);
        $subquery = new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($one)]));
        $rules = new PartitionExpressions();

        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Function, $rules->condition($row, GrammarRelease::MySql847));
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::SessionState, $rules->condition($session, GrammarRelease::MySql847));
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery, $rules->condition($subquery, GrammarRelease::MySql847));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerExpressions(): iterable
    {
        yield 'session function' => ['USER()', true];
        yield 'nested nondeterminism' => ['ABS(RAND())', true];
        yield 'system variable' => ['@@sql_mode', true];
        yield 'user variable' => ['@x', true];
        yield 'clock' => ['NOW()', true];
        yield 'stored function' => ['custom_function(1)', true];
        yield 'server version checked later' => ['VERSION()', false];
        yield 'constant checked later' => ['ABS(1)', false];
        yield 'deterministic disallowed function checked later' => ['SIN(1)', false];
        yield 'timestamp conversion checked later' => ['UNIX_TIMESTAMP(1)', false];
        yield 'current timestamp' => ['UNIX_TIMESTAMP()', true];
    }

    #[DataProvider('providerExpressions')]
    public function testForbiddenDistinguishesEarlyAndLaterChecks(string $sql, bool $forbidden): void
    {
        $expression = (new Semantics(Dialect::MySql))->analyze('SELECT ' . $sql)->field(0)->expression;
        self::assertInstanceOf(Scalar::class, $expression);
        self::assertSame($forbidden, (new PartitionExpressions())->forbidden($expression, GrammarRelease::MySql847));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerEarlierErrors(): iterable
    {
        yield 'unknown system variable' => ['@@unknown_system_variable'];
    }

    #[DataProvider('providerEarlierErrors')]
    public function testDerivePreservesEarlierFunctionAndVariableErrors(string $expression): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operand = $semantics->analyze('SELECT ' . $expression)->field(0)->expression;
        self::assertInstanceOf(Scalar::class, $operand);
        $operand = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($operand);
        $derivation = new \SqlSemantics\Construction\Derivation($semantics->context());
        (new PartitionExpressions())->derive($operand, $derivation, $derivation->environment());
        self::assertNotEmpty($derivation->facts()->diagnostics);
        self::assertCount(1, $derivation->facts()->warnings);
        self::assertInstanceOf(ParseFailure::class, $derivation->facts()->warnings[0]);
        self::assertSame($derivation->facts()->diagnostics[0], $derivation->facts()->warnings[0]->problem);
    }

    public function testDerivePreservesWarningsAndTheOriginalOccurrenceLocation(): void
    {
        $sql = 'ALTER TABLE missing PARTITION BY HASH ((FOUND_ROWS()))';
        $semantics = new Semantics(Dialect::MySql);
        $statement = $semantics->analyze($sql);
        self::assertInstanceOf(Deprecation::class, $statement->warnings()[0]);
        $failure = $statement->warnings()[1];
        self::assertInstanceOf(ParseFailure::class, $failure);
        self::assertTrue($failure->aborts);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization, $failure->phase);
        self::assertInstanceOf(InvalidPartitionExpression::class, $failure->problem);
        $origin = $failure->problem->expression->source();
        self::assertNotNull($origin);
        self::assertSame('(FOUND_ROWS())', substr($sql, $origin->offset, $origin->length));
    }
    public function testForbiddenSeparatesIntrinsicStateDependenceFromVersionedNativeFunctions(): void
    {
        $rules = new PartitionExpressions();
        $name = new \SqlSemantics\Statement\Identifier\Name('JSON_VALID');
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall($name, [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        self::assertTrue($rules->forbidden($call, GrammarRelease::MySql5651));
        self::assertFalse($rules->forbidden($call, GrammarRelease::MySql847));
        self::assertTrue($rules->forbidden(new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User, []), GrammarRelease::MySql847));
    }

    public function testDeriveCannotReceiveAnInvalidNativeSignature(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Incorrect parameter count in the call to native function 'RAND'");

        (new Semantics(Dialect::MySql))->analyze('ALTER TABLE missing PARTITION BY HASH (RAND(1,2))');
    }

}
