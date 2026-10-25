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
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightStringParameters as Model;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class WeightStringParametersProperties extends TestCase
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
     * @return iterable<string, array{string, Scalar}>
     */
    public static function providerWeightPacketLengths(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($index = 0; $index < 100; $index++) {
                $choices = new Choices('weight-packet:' . $index);
                $value = $choices->one(['1', '65536', '1073741824', '2147483648', '4294967295', '4294967296', '18446744073709551615', (string) $choices->integer(1, 4294967295)]);
                $subject = new StringLiteral(['abc']);
                $length = new Numeral($value);
                $node = match ($choices->integer(0, 3)) {
                    0 => new WeightString($subject, WeightCast::Char, $length),
                    1 => new WeightString($subject, WeightCast::Binary, $length),
                    2 => new Model($subject, $length, new Numeral('1'), new Numeral('128')),
                    default => new Model($subject, new Numeral('1'), $length, new Numeral('128')),
                };
                yield $release . ':' . $index => [$release, $node];
            }
        }
    }

    /**
     * Large values are legal structures; execution returns bounded SQL values and conditions.
     */
    #[DataProvider('providerWeightPacketLengths')]
    #[Test]
    public function satisfiesConstructionCannotForceAnUnboundedWeightAllocation(string $release, Scalar $node): void
    {
        $session = (new Instance($release, globals: ['max_allowed_packet' => 4096, 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_bin']))->connect();
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement($node);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $outcome = $result1;
        self::assertNotInstanceOf(SqlError::class, $outcome);
        self::assertInstanceOf(ResultSet::class, $outcome);
        $rows = $outcome->rows;
        self::assertCount(1, $rows);
        $value = $rows[0][0];
        self::assertLessThanOrEqual(4096, strlen((string) $value));
        self::assertSame('unrelated invalid input', $session->text);
    }
}
