<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Utility\Explain;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help as Model;
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
final class HelpProperties extends TestCase
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
     * @return iterable<string, array{string, int}>
     */
    public static function providerHelpProfiles(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Constructor-generated searches remain executable with unrelated source text and changing data.
     *
     * @throws JsonException When the semantic inventory is invalid
     */
    #[DataProvider('providerHelpProfiles')]
    #[Test]
    public function satisfiesConstructedSearchUsesTheSemanticName(string $release, int $seed): void
    {
        $choices = new Choices('help-structure:' . $seed);
        $statement = (new Construction($choices, new Catalogue(), new Metadata()))->object(Model::class, 4);
        self::assertInstanceOf(Model::class, $statement);
        $name = $statement->topic->value;
        $text = 'fixture:' . $choices->integer(0, 100000);
        $empty = (new Instance($release, globals: ['character_set_results' => 'utf8mb4']))->connect();
        $full = (new Instance($release, globals: ['character_set_results' => 'utf8mb4'], help: ['topics' => [['1', $name, '1', $text, 'Example']]]))->connect();
        $empty->text = $full->text = 'unrelated invalid SQL';
        $statement1 = (new Embedding())->statement($statement);
        $empty->activity->begin();
        StatementCounters::received($empty);
        $empty->hinted = [];
        $empty->dots = [];
        $operation1 = (new Binding())->operation($empty, $statement1, [], new LanguageProfile($empty->semantics()->profile()->grammar, $empty->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $missing = (new Execution($empty))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement($statement);
        $full->activity->begin();
        StatementCounters::received($full);
        $full->hinted = [];
        $full->dots = [];
        $operation2 = (new Binding())->operation($full, $statement2, [], new LanguageProfile($full->semantics()->profile()->grammar, $full->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $found = (new Execution($full))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $missing);
        self::assertInstanceOf(ResultSet::class, $found);
        self::assertSame([], $missing->rows);
        self::assertSame([[$name, $text, 'Example']], $found->rows);
        self::assertSame(['name', 'description', 'example'], array_column($found->columns, 'name'));
    }
}
