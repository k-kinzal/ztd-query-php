<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Expression\Access;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn as Model;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Context\Columns;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class DefaultOfColumnProperties extends TestCase
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new \Tests\Generator\Choices('context:' . $sample));
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
     * The original DEFAULT column reference resolves through the supplied storage names.
     */
    #[Test]
    public function satisfiesKeepsAnEmptyDefaultColumnAliasAndUsesOrdinaryStorageNames(): void
    {
        $column = new ColumnUse(new Name(''));
        $scalar = new Model($column);
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(QueryRoot::class, $statement);
        self::assertInstanceOf(Select::class, $statement->query);
        self::assertInstanceOf(SelectExpression::class, $statement->query->items[0]);
        self::assertSame($scalar, $statement->query->items[0]->expression);
        $binding = (new Columns())->bindings($scalar)[0];
        self::assertSame('', $binding['names'][0]->value);
        self::assertSame('fixture_column_0', $binding['columns'][0]->value);
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE structure_fixture; CREATE TABLE structure_fixture.input_0(fixture_column_0 INT DEFAULT 7); INSERT INTO structure_fixture.input_0 VALUES (3)');
        $session->text = 'opaque input';
        $statement3 = (new Embedding())->statement($scalar);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $result = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertSame('opaque input', $session->text);
    }
}
