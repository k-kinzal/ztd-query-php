<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Utility\Explain;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection as Model;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ExplainConnectionProperties extends TestCase
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
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, Numeral}>
     * @throws JsonException When the reviewed inventory is invalid
     * @throws RuntimeException When the bounded sample contains too few positive thread identities
     */
    public static function providerExplainConnectionIdentifiers(): iterable
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $count = 0;
        for ($seed = 0; $seed < 1000 && $count < 25; $seed++) {
            $value = (new Construction(new Choices('explain-identifier:' . $seed), $catalogue, $metadata))->object(Model::class, 4);
            if ($value instanceof Model && $value->connectionId > 0) {
                $count++;
                foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
                    yield $release . ':' . $seed => [$release, $value->connection];
                }
            }
        }
        if ($count !== 25) {
            throw new RuntimeException('The bounded sample did not produce 25 positive connection identifiers.');
        }
    }

    /**
     * The same constructed operand fails without its connection and completes once that state exists.
     */
    #[DataProvider('providerExplainConnectionIdentifiers')]
    #[Test]
    public function satisfiesGeneratedIdentifierResolvesTheExistingConnection(string $release, Numeral $number): void
    {
        $statement = new Model($number);
        $instance = new Instance($release);
        $session = $instance->connect(threadId: $statement->connectionId === 1 ? 2 : 1);
        $session->text = 'unrelated invalid SQL';
        $failure = null;
        try {
            $statement1 = (new Embedding())->statement($statement);
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        } catch (SqlError $error) {
            $failure = $error;
        }
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame(1094, $failure->getCode());
        self::assertSame('Unknown thread id: ' . $statement->connectionId, $failure->getMessage());
        $target = $instance->connect(threadId: $statement->connectionId);
        $statement2 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []));
        self::assertSame($statement->connectionId, $target->id);
    }
}
