<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Call\Weight;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString as Model;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class WeightStringProperties extends TestCase
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
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, WeightCast, Numeral}>
     */
    public static function providerWeightLengthLengths(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($index = 0; $index < 20; $index++) {
                $choices = new Choices('weight-length:' . $index);
                yield $release . ':' . $index => [$release, $choices->one(WeightCast::cases()), $choices->one([new Numeral('1'), new Numeral('00001'), new Numeral('2'), new Numeral('A', true), new Numeral('20', true), new Numeral('4294967296'), new Numeral('18446744073709551615')])];
            }
        }
    }

    /**
     * The constructed value executes directly, without a SQL source or an eligibility check.
     */
    #[DataProvider('providerWeightLengthLengths')]
    #[Test]
    public function satisfiesPositiveLengthsExecuteInTheirDeclaredDomain(string $release, WeightCast $cast, Numeral $length): void
    {
        $node = new Model(new NullLiteral(), $cast, $length);
        $session1 = (new Instance($release))->connect();
        $statement1 = (new Embedding())->statement($node);
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $result = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    /**
     * @return iterable<string, array{string, Numeral, int}>
     */
    public static function providerWeightLengthSpellings(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($index = 0; $index < 20; $index++) {
                $choices = new Choices('weight-spelling:' . $index);
                [$spelling, $length] = $choices->one([[new Numeral('00010'), 10], [new Numeral('A', true), 10], [new Numeral('20', true), 32], [new Numeral('0001'), 1], [new Numeral('F', true), 15], [new Numeral('4294967297'), 1]]);
                yield $release . ':' . $index => [$release, $spelling, $length];
            }
        }
    }

    /**
     * The requested binary length governs padding and truncation, independently of token spelling.
     */
    #[DataProvider('providerWeightLengthSpellings')]
    #[Test]
    public function satisfiesNumericSpellingDoesNotChangeTheResolvedCast(string $release, Numeral $spelling, int $length): void
    {
        $subject = new StringLiteral(['abcdefghij']);
        $node = new Model($subject, WeightCast::Binary, $spelling);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $statement2 = (new Embedding())->statement($node);
        $parameters2 = array_fill(0, (new Bindings())->count($statement2), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation2 = (new Binding())->operation($session, $statement2, $parameters2, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
            $result2 = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), $parameters2);
        } catch (SqlError $executionError) {
            $result2 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $result = $result2;
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[str_pad(substr('abcdefghij', 0, $length), $length, "\x00")]], $result->rows);
        self::assertSame('unrelated invalid input', $session->text);
    }
}
