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
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast as Model;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class BinaryCastProperties extends TestCase
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
     * @return iterable<string, array{string, int, bool}>
     */
    public static function providerBinaryOperandOperands(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 12; $seed++) {
                foreach ([false, true] as $arithmetic) {
                    yield $release . ':' . $seed . ':' . (int) $arithmetic => [$release, $seed, $arithmetic];
                }
            }
        }
    }

    /**
     * Submits generated objects directly and checks values and ordered warnings.
     */
    #[DataProvider('providerBinaryOperandOperands')]
    #[Test]
    public function satisfiesCompletionUsesTheRetainedOperandStructure(string $release, int $seed, bool $arithmetic): void
    {
        $choices = new Choices('binary-operand:' . $seed);
        $value = $choices->integer(0, 10000);
        $operand = new Cast(new NumberLiteral((string) $value), new CastTarget(CastKind::Signed));
        if ($arithmetic) {
            $operand = new Grouped(new Arithmetic(ArithmeticOperator::Plus, $operand, new NumberLiteral('1')));
        }
        for ($depth = $choices->integer(0, 12); $depth > 0; $depth--) {
            $operand = new Grouped($operand);
        }
        $query = new Select([], [new SelectExpression(new Model($operand)), new SelectExpression(new Model(new NumberLiteral('2')))]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $statement1 = (new Embedding())->statement($query);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) ($value + ($arithmetic ? 1 : 0)), '2']], $reply->rows);
        self::assertSame([1287, 1287], array_column($session->diagnostics->conditions, 1));
    }
}
