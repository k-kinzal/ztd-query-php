<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use Eris\TestTrait;
use LogicException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\Prepared\PreparedStatement;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Operation;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;
use Tests\Generator\Semantics\Statement\OperationGenerator;

/**
 * Checks that MemoryDB consumes generated column-definition names through CREATE TABLE execution.
 */
#[CoversNothing]
#[Large]
final class CreateTableExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * A fresh table must contain the generated decoded name after the semantic operation executes.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCreatedColumnRetainsItsName(GrammarRelease $release): void
    {
        $this->forAll(new OperationGenerator($release))->then(static function (Operation $operation) use ($release): void {
            $database = $operation->context->declarationSchema->value;
            $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);

            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement));

            self::assertInstanceOf(Completion::class, $reply);
            self::assertInstanceOf(CreateTable::class, $operation->statement);
            $definition = $operation->statement->elements[0];
            self::assertInstanceOf(ColumnDefinition::class, $definition);
            $schema = $session->instance->dictionary->schema($database);
            self::assertNotNull($schema);
            $table = $schema->table($operation->statement->name->name->value);
            self::assertNotNull($table);
            self::assertCount(1, $table->definition->columns);
            self::assertSame($definition->name->column->value, $table->definition->columns[0]->name);
        });
    }

    /**
     * Contradictory requests remain executable values and fail with their SQL condition.
     * Operation is still the transitional publication boundary for table definitions.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesDuplicateColumnsFailWithoutInstallingATable(GrammarRelease $release): void
    {
        $requests = new OperationGenerator($release, new CreateTableGenerator(new ColumnNameGenerator($release), duplicateColumns: true, ifNotExists: true));
        $this->forAll($requests)->then(static function (Operation $operation) use ($release): void {
            $database = $operation->context->declarationSchema->value;
            $session = (new Instance(substr($release->value, 6), [], [$database]))->connect(database: $database);
            $session->text = 'generated duplicate-column request';
            try {
                (new Execution($session))->statement($operation);
                self::fail('Duplicate columns cannot install a table.');
            } catch (SqlError $error) {
                self::assertSame(1060, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema($database)?->tables);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
            self::assertSame('generated duplicate-column request', $session->text);
        });
    }

    /**
     * Preparation retains the request; execution observes a destination installed later.
     * Eris shrinks both requests together before the execution callback runs.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesPreparedConditionalCreationUsesTheCurrentDestination(GrammarRelease $release): void
    {
        $sequences = Generators::map(static function (Operation $operation): array {
            $create = $operation->statement;
            if (!$create instanceof CreateTable || !$create->elements[0] instanceof ColumnDefinition) {
                throw new LogicException('The creation generator supplies a table and its column.');
            }
            $name = $create->elements[0]->name;
            $duplicate = new ColumnDefinition(new ColumnName($name->column, $name->table, $name->dot), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
            return [$operation, new Operation($operation->context, new CreateTable($create->name, [$create->elements[0], $duplicate], ifNotExists: true))];
        }, new OperationGenerator($release));
        $this->forAll($sequences)->then(static function (array $sequence) use ($release): void {
            [$prerequisite, $request] = $sequence;
            self::assertInstanceOf(Operation::class, $prerequisite);
            self::assertInstanceOf(Operation::class, $request);
            $database = $prerequisite->context->declarationSchema->value;
            $session = (new Instance(substr($release->value, 6), [], [$database]))->connect(database: $database);
            (new \MySqlMemory\Session\Access\Preparation())->check($request, $session);
            $prepared = PreparedStatement::capture($request, $session);
            self::assertInstanceOf(Completion::class, (new Execution($session))->statement($prerequisite));
            $tables = $session->instance->dictionary->schema($database)?->tables;
            $reply = $prepared->run($session, 'generated conditional creation', [])[0];
            if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                self::assertInstanceOf(SqlError::class, $reply);
                self::assertSame(1060, $reply->getCode());
            } else {
                self::assertInstanceOf(Completion::class, $reply);
                self::assertSame([1050], array_column($session->diagnostics->conditions, 1));
            }
            self::assertSame($tables, $session->instance->dictionary->schema($database)?->tables);
            self::assertSame($request->statement, $prepared->statement);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }
}
