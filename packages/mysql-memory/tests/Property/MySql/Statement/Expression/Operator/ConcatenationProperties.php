<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Expression\Operator;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation as Model;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ConcatenationProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLexicalModesSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * @throws \MySqlMemory\Error\SqlError When the directly constructed query cannot execute
     */
    #[DataProvider('providerLexicalModesSeeds')]
    #[Test]
    public function satisfiesModeSpecificStructuresPreserveTheirMeaning(string $seed): void
    {
        $choices = new Choices('lexical-structures:' . $seed);
        $high = $choices->one([true, false]);
        $concat = $choices->one([true, false]);
        $left = $choices->integer(0, 20);
        $right = $choices->integer(0, 20);
        $negation = $high ? new Unary(UnaryOperator::Not, new NumberLiteral((string) $left), true) : new Not(new NumberLiteral((string) $left));
        $pipes = $concat ? new Model(new NumberLiteral((string) $left), new NumberLiteral((string) $right)) : new Logical(LogicalOperator::Or, new NumberLiteral((string) $left), new NumberLiteral((string) $right), true);
        $comparison = $high ? new Comparison(ComparisonOperator::Equal, new Unary(UnaryOperator::Not, new NumberLiteral((string) $left), true), new NumberLiteral((string) $right)) : new Not(new Comparison(ComparisonOperator::Equal, new NumberLiteral((string) $left), new NumberLiteral((string) $right)));
        $statement = new Select([], [new SelectExpression($negation, new Name('negation')), new SelectExpression($pipes, new Name('pipes')), new SelectExpression($comparison, new Name('precedence'))]);
        $mode = implode(',', array_filter([$high ? 'HIGH_NOT_PRECEDENCE' : '', $concat ? 'PIPES_AS_CONCAT' : '']));
        $session1 = (new Instance($choices->one(['8.0.44', '8.4.7', '9.1.0'])))->connect();
        $session1->variables->session['sql_mode'] = $mode;
        $statement1 = (new Embedding())->statement($statement);
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) (int) ($left === 0), $concat ? (string) $left . $right : (string) (int) ($left !== 0 || $right !== 0), (string) (int) ($high ? (int) ($left === 0) === $right : $left !== $right)]], $reply->rows);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerProfileOperands(): iterable
    {
        for ($seed = 0; $seed < 20; $seed++) {
            $choices = new Choices('profiles:' . $seed);
            yield (string) $seed => [$choices->one(['a', '0', "'", '\\', 'あ']), $choices->one(['b', '1', "'", '\\', 'い'])];
        }
    }
    /**
     * Generated concatenation operands retain their lexical interpretation during execution.
     */
    #[DataProvider('providerProfileOperands')]
    #[Test]
    public function satisfiesUsesLexicalRequirementsBeforeExecution(string $left, string $right): void
    {
        $node = new Model(new StringLiteral([$left]), new StringLiteral([$right]));
        $profiles = new Profiles();
        $profile = $profiles->select($node);
        $session1 = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session1->variables->session['sql_mode'] = $profiles->mode($profile);
        $statement1 = (new Embedding())->statement($node);
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertTrue($profile->lexical->pipesAsConcat);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$left . $right]], $reply->rows);
    }
}
