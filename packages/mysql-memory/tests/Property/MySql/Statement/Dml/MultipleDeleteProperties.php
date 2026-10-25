<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Dml;

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
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete as Model;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm;
use SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class MultipleDeleteProperties extends TestCase
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
     * @return iterable<string, array{string, int, MultipleDeleteForm, bool}>
     */
    public static function providerDeleteListsLists(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                $choices = new Choices('delete-lists:' . $seed);
                yield $release . ':' . $seed => [$release, $choices->one([1, 2, 3, 4]), $choices->one(MultipleDeleteForm::cases()), $choices->one([false, true])];
            }
        }
    }

    /**
     * The generated statement executes directly; SQL is used only to establish and observe fixture state.
     */
    #[DataProvider('providerDeleteListsLists')]
    #[Test]
    public function satisfiesComposedReferencesDeleteEachTargetRowOnce(string $release, int $rows, MultipleDeleteForm $form, bool $nested): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT); CREATE TABLE b (id INT); CREATE TABLE c (id INT)');
        for ($row = 0; $row < $rows; $row++) {
            $session->query('INSERT INTO a VALUES (' . $row . '); INSERT INTO b VALUES (' . $row . '); INSERT INTO c VALUES (' . $row . ')');
        }
        $list = new TableList([new TableReference(new QualifiedName(new Name('b'))), new TableReference(new QualifiedName(new Name('c')))]);
        $statement = new Model(null, [], new DeletionInput([new TableReference(new QualifiedName(new Name('a'))), $nested ? new NestedRelation($list) : $list], [new QualifiedName(new Name('a')), new QualifiedName(new Name('b'))]), $form);
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $remaining = $session->query('SELECT (SELECT COUNT(*) FROM a), (SELECT COUNT(*) FROM b), (SELECT COUNT(*) FROM c)')[0];
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame($rows * 2, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $remaining);
        self::assertSame([['0', '0', (string) $rows]], $remaining->rows);
    }
}
