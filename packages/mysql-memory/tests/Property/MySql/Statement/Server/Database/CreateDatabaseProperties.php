<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Server\Database;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase as Model;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Validation\Equivalence;
use SqlSemantics\Validation\Publication;
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
final class CreateDatabaseProperties extends TestCase
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
    public static function providerDatabaseOptionInputs(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, (string) $seed];
            }
        }
    }

    /**
     * Generated encryption settings execute directly and retain their rendering correspondence.
     *
     * @throws JsonException When the reviewed inventory is invalid
     */
    #[DataProvider('providerDatabaseOptionInputs')]
    #[Test]
    public function satisfiesGeneratedEncryptionOptionsExecuteAndCorrespondToTheProfile(string $release, string $seed): void
    {
        $option = (new Construction(new Choices('database-encryption:' . $seed), new Catalogue(), new Metadata()))->object(DatabaseEncryption::class, 6);
        self::assertInstanceOf(DatabaseEncryption::class, $option);
        $statement = new AlterDatabase(new Name('d'), [$option]);
        $profile = new LanguageProfile(GrammarRelease::from('mysql-' . $release));
        $platform = Platforms::of('mysql');
        $out = new Output($platform->codec($profile));
        $statement->render($out);
        $sql = (new Lexical())->join($out->pieces());
        $lowered = $platform->lower($platform->parser($profile)->parse($sql), $profile, new Leaves());
        self::assertNull((new Equivalence())->difference($statement, (new Publication())->root($lowered)));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $statement1 = (new Embedding())->statement(new Model(false, new Name('d'), [$option]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertNotNull($session->instance->dictionary->schema('d'));
        self::assertSame($option->encryption, $session->instance->dictionary->schema('d')->encryption);
        $opposite = $option->encryption === Encryption::Enabled ? Encryption::Disabled : Encryption::Enabled;
        $statement2 = (new Embedding())->statement(new AlterDatabase(new Name('d'), [new DatabaseEncryption($opposite)]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertSame($opposite, $session->instance->dictionary->schema('d')?->encryption);
        $statement3 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $result = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertInstanceOf(Completion::class, $result);
        self::assertSame(1, $result->affectedRows);
        self::assertSame([], $session->diagnostics->conditions);
        self::assertSame($option->encryption, $session->instance->dictionary->schema('d')?->encryption);
        $statement4 = (new Embedding())->statement(new ShowCreateDatabase(new Name('d')));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation4 = (new Binding())->operation($session, $statement4, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
        $shown = (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), []);
        self::assertInstanceOf(ResultSet::class, $shown);
        self::assertSame("CREATE DATABASE `d` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='" . $option->encryption->value . "' */", $shown->rows[0][1]);
    }

    /**
     * The option's public constructor supplies its invariant before execution.
     *
     * @throws JsonException When the reviewed inventory is invalid
     */
    #[DataProvider('providerDatabaseOptionInputs')]
    #[Test]
    public function satisfiesGeneratedReadOnlyOptionsHaveExecutableOperands(string $release, string $seed): void
    {
        $option = (new Construction(new Choices('database-option:' . $seed), new Catalogue(), new Metadata()))->object(DatabaseReadOnly::class, 6);
        self::assertInstanceOf(DatabaseReadOnly::class, $option);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $statement5 = (new Embedding())->statement(new Model(false, new Name('d')));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation5 = (new Binding())->operation($session, $statement5, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
        (new Execution($session))->perform($operation5, (new Dispatcher())->command($operation5->statement), []);
        $statement6 = (new Embedding())->statement(new AlterDatabase(new Name('d'), [$option]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation6 = (new Binding())->operation($session, $statement6, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement6)));
        $result = (new Execution($session))->perform($operation6, (new Dispatcher())->command($operation6->statement), []);
        self::assertInstanceOf(Completion::class, $result);
        self::assertSame(1, $result->affectedRows);
        self::assertSame([], $session->diagnostics->conditions);
    }
}
