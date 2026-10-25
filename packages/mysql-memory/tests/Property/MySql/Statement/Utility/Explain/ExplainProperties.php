<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Utility\Explain;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain as Model;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ExplainProperties extends TestCase
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
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerExplainFormatFormats(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['TRADITIONAL', 'JSON', ...str_starts_with($release, '5.') ? [] : ['TREE']] as $format) {
                for ($seed = 0; $seed < 10; $seed++) {
                    yield $release . ':' . $format . ':' . $seed => [$release, $format, $seed];
                }
            }
        }
    }

    /**
     * Constructed formats explain a generated scalar and identify an existing idle connection.
     * Neither operation consumes the unrelated SQL text attached to its session.
     */
    #[DataProvider('providerExplainFormatFormats')]
    #[Test]
    public function satisfiesConstructedFormatsRemainExecutable(string $release, string $format, int $seed): void
    {
        $choices = new Choices('explain-format:' . $seed);
        $name = implode('', array_map(static fn (string $letter): string => $choices->one([$letter, strtolower($letter)]), str_split($format)));
        $value = new NumberLiteral((string) $choices->integer(0, 100000));
        $statement = new Model(new QueryRoot(new Select([], [new SelectExpression($value)])), new Name($name));
        $instance = new Instance($release);
        $session = $instance->connect();
        $target = $instance->connect(threadId: 255);
        $session->text = 'unrelated invalid SQL';
        $statement1 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount($format === 'TRADITIONAL' ? 12 : 1, $reply->columns);
        $statement2 = (new Embedding())->statement(new ExplainConnection(new Numeral((string) $target->id), new Name($name)));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []));
    }

    /**
     * @throws JsonException When a semantic inventory is invalid
     */
    #[Test]
    public function satisfiesPreservesExplainAfterRejectingMixedDeleteGenerations(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('row-roles-20261017:187');
        $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object(Model::class, 6);
        self::assertInstanceOf(Model::class, $node);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
        $subject = $node;
        $profile = (new Profiles())->select($subject);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $statement = (new Embedding())->statement($subject);
        $parameters = array_fill(0, (new Bindings())->count($statement), [null, Domain::null()]);
        try {
            $operation = (new Binding())->operation($session, $statement, $parameters, $profile);
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $failure) {
            self::assertTrue($failure->signalled !== null || !in_array($failure->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), $failure->getMessage());
        }
    }
}
