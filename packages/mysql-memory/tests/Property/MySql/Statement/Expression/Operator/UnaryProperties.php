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
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary as Model;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class UnaryProperties extends TestCase
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
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
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
     * @return iterable<string, array{string, string}>
     */
    public static function providerNegationTrials(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 25; $seed++) {
                yield $release . ':' . $seed => [$release, (string) $seed];
            }
        }
    }

    /**
     * @throws \MySqlMemory\Error\SqlError When a constructed negation cannot execute
     */
    #[DataProvider('providerNegationTrials')]
    #[Test]
    public function satisfiesNegationCompositionsExecuteWithoutSourceSql(string $release, string $seed): void
    {
        $choices = new Choices('negation-structures:' . $seed);
        $value = $choices->one([null, 0, 1, 7, 65535]);
        $operators = array_map(static fn (int $position): UnaryOperator => $choices->one([UnaryOperator::Not, UnaryOperator::PairedNot]), range(1, $choices->integer(1, 6)));
        $expression = array_reduce($operators, static fn (Scalar $operand, UnaryOperator $operator): Model => new Model($operator, $operand), $value === null ? new NullLiteral() : new NumberLiteral((string) $value));
        $session1 = (new Instance($release))->connect();
        $statement1 = (new Embedding())->statement($expression);
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $expected = $value === null ? null : (string) (int) (count($operators) % 2 === 0 ? $value !== 0 : $value === 0);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$expected]], $reply->rows);
        self::assertSame(str_starts_with($release, '5.') ? 0 : count($operators), $reply->warnings);
    }
}
