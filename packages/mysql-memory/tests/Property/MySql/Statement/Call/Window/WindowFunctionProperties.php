<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Call\Window;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Reply;
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
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction as Model;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class WindowFunctionProperties extends TestCase
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
    public static function providerWindowOperandDomainsCounts(): iterable
    {
        $choices = new Choices('window-count-domain-20261017');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($trial = 0; $trial < 20; $trial++) {
                yield $release . ':' . $trial => [$release, $choices->integer(1, 100)];
            }
        }
    }

    /**
     * A populated query keeps its structure while the window count changes between SQL error and success.
     */
    #[DataProvider('providerWindowOperandDomainsCounts')]
    #[Test]
    public function satisfiesTheSameCountReferenceFollowsSessionValues(string $release, int $count): void
    {
        $statement = new QueryRoot(new Select([], [new SelectExpression(new Model(WindowFunctionKind::Tile, [new UserVariable(new Name('n'))], new WindowSpec()))], new TableReference(new QualifiedName(new Name('t')))));
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2),(3)');
        foreach (['0', (string) $count, 'NULL', '1'] as $index => $value) {
            $session->query('SET @n=' . $value);
            $session->text = 'unrelated invalid input';
            $statement1 = (new Embedding())->statement($statement);
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
            $observation = $result1;
            self::assertSame($index === 0 || $index === 2 ? 1210 : null, ($observation instanceof SqlError ? [$observation->getCode(), $observation->sqlState(), $observation->getMessage()] : null)[0] ?? null);
            self::assertSame($index === 0 || $index === 2 ? 0 : 1, count($observation instanceof Reply ? [$observation] : []));
        }
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * A generated window call executes with an ambient definition while retaining its identity.
     */
    #[Test]
    public function satisfiesSuppliesTheAmbientWindowWithoutReplacingTheOriginalScalar(): void
    {
        $call = new Model(WindowFunctionKind::RowNumber, [], new Name('w'));
        $statement = (new Embedding())->statement($call);
        self::assertInstanceOf(QueryRoot::class, $statement);
        self::assertInstanceOf(Select::class, $statement->query);
        self::assertInstanceOf(SelectExpression::class, $statement->query->items[0]);
        self::assertSame($call, $statement->query->items[0]->expression);
        self::assertSame('w', $statement->query->windows[0]->name->value);
        self::assertSame($statement, (new Embedding())->statement($statement));
        $session2 = (new Instance('8.4.7'))->connect();
        $statement2 = (new Embedding())->statement($call);
        $session2->activity->begin();
        StatementCounters::received($session2);
        $session2->hinted = [];
        $session2->dots = [];
        $operation2 = (new Binding())->operation($session2, $statement2, [], new LanguageProfile($session2->semantics()->profile()->grammar, $session2->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $result = (new Execution($session2))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame(GrammarRelease::MySql8044, (new Profiles())->select($call)->grammar);
    }
}
