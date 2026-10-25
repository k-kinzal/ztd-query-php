<?php

declare(strict_types=1);

namespace Tests\Property\Semantics\Statement;

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
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Statement\Scalar as Model;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;

/**
 * Generates members of the semantic interface and consumes their original values in MemoryDB.
 */
#[CoversNothing]
#[Large]
final class ScalarProperties extends TestCase
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
     * The semantic interface inhabited by each generated sample.
     */
    public const MODEL = Model::class;

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function providerConstructionOperands(): iterable
    {
        foreach ([NumberLiteral::class, BooleanLiteral::class, NullLiteral::class, Parameter::class] as $class) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $class . ':' . $seed => [$class, (string) $seed];
            }
        }
    }
    /**
     * @throws JsonException When the value inventory is invalid
     * @param class-string $class
     */
    #[DataProvider('providerConstructionOperands')]
    #[Test]
    public function satisfiesGeneratedOperandExecutesWithoutSqlInput(string $class, string $seed): void
    {
        $construction = new Construction(new Choices($seed), new Catalogue(), new Metadata());
        $literal = $construction->object($class, 4);
        self::assertInstanceOf(Model::class, $literal);
        $session1 = (new Instance('8.4.7'))->connect();
        $statement1 = (new Embedding())->statement($literal);
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount(1, $reply->rows);
        self::assertCount(1, $reply->rows[0]);
    }
}
