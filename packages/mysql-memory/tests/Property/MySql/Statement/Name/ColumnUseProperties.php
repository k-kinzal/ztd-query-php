<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Name;

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
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse as Model;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Context\Columns;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ColumnUseProperties extends TestCase
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
     * @return iterable<int, array{int}>
     */
    public static function providerEmbeddingSeeds(): iterable
    {
        foreach (range(0, 29) as $seed) {
            yield [$seed];
        }
    }
    /**
     * Generated free column references retain identity when bound to populated relations.
     */
    #[DataProvider('providerEmbeddingSeeds')]
    #[Test]
    public function satisfiesKeepsFreeScalarIdentityAcrossPopulatedBindings(int $seed): void
    {
        $choices = new Choices('ambient-columns:' . $seed);
        $name = new Name('c' . $choices->integer(0, 1000));
        $qualifier = $choices->one([null, new QualifiedName(new Name('a.b')), new QualifiedName(new Name('t'), new Name('d'))]);
        $scalar = new Model($name, $qualifier);
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(QueryRoot::class, $statement);
        self::assertInstanceOf(Select::class, $statement->query);
        self::assertInstanceOf(SelectExpression::class, $statement->query->items[0]);
        self::assertSame($scalar, $statement->query->items[0]->expression);
        $binding = (new Columns())->bindings($scalar)[0];
        $table = $binding['relation']->name;
        $schema = $table->schema->value ?? 'structure_fixture';
        $session = (new Instance(databases: [$schema]))->connect(database: $schema);
        $identifier = '`' . str_replace('`', '``', $table->name->value) . '`';
        $session->query('CREATE TABLE ' . $identifier . ' (`' . $name->value . '` INT); INSERT INTO ' . $identifier . ' VALUES (7)');
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement($scalar);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $result = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertSame('unrelated invalid input', $session->text);
    }
}
