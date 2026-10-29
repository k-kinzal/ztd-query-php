<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
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
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;
use Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator;

/**
 * Checks that MemoryDB consumes generated column-definition names through CREATE TABLE execution.
 */
#[CoversNothing]
#[Large]
final class CreateTableExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Keyword synonyms retain the same installed domains and write coercions as their canonical type.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesTypeSynonymsPreserveDependentWrites(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator::synonymWrites($release))->then(static function (array $sample) use ($release): void {
            [$create, $insert, $query] = $sample;
            self::assertInstanceOf(CreateTable::class, $create);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $insert);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'generated type synonyms and dependent values';
            $execution = new Execution($session);
            self::assertInstanceOf(Completion::class, $execution->statement($create));
            $stored = $session->instance->dictionary->schema('pbt')?->table('type_pair');
            self::assertNotNull($stored);
            self::assertEquals($stored->definition->columns[0]->domain, $stored->definition->columns[1]->domain);
            $written = $execution->statement($insert);
            self::assertInstanceOf(Completion::class, $written);
            self::assertSame(1, $written->affectedRows);
            $reply = $execution->statement($query);
            self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $reply);
            self::assertCount(1, $reply->rows);
            self::assertCount(2, $reply->columns);
            self::assertSame($reply->rows[0][0], $reply->rows[0][1]);
            self::assertSame($reply->columns[0]->type, $reply->columns[1]->type);
            self::assertSame($reply->columns[0]->length, $reply->columns[1]->length);
            self::assertSame($reply->columns[0]->decimals, $reply->columns[1]->decimals);
            self::assertSame($reply->columns[0]->flags, $reply->columns[1]->flags);
            self::assertSame('generated type synonyms and dependent values', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Optional notation preserves option values; MySQL 5.6 applies its default Antelope format fallback.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedOptionsInstallTheirDeclaredValues(GrammarRelease $release): void
    {
        $this->forAll(CreateTableGenerator::optionDefinitions($release))->then(static function (array $sample) use ($release): void {
            [, $operation] = $sample;
            self::assertInstanceOf(CreateTable::class, $operation);
            $database = $operation->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), databases: [$database]))->connect(database: $database);
            $session->text = 'direct generated table options';
            $reply = (new Execution($session))->statement($operation);
            self::assertInstanceOf(Completion::class, $reply);
            $definition = $session->instance->dictionary->schema($database)?->table($operation->name->name->value)?->definition;
            self::assertNotNull($definition);
            self::assertSame('InnoDB', $definition->engine);
            self::assertSame('latin1_swedish_ci', $definition->collation);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption::class, $operation->options[2]);
            self::assertSame($operation->options[2]->value->value, (new \MySqlMemory\Command\Show\ShowCreateTableCommand())->comment($definition));
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption::class, $operation->options[3]);
            self::assertSame($release === GrammarRelease::MySql5651 ? \SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Compact : $operation->options[3]->format, $definition->rowFormat);
            self::assertSame('direct generated table options', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * An incompatible effective default remains a semantic request and raises its SQL condition.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesIncompatibleDefaultsFailBeforeInstallingATable(GrammarRelease $release): void
    {
        $types = new \Tests\Generator\MySql\Statement\Type\TemporalGenerator(
            Generators::elements([\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::DateTime, \SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::Timestamp]),
            Generators::elements(['1', '2', '3', '4', '5', '6']),
        );
        $attributes = \Tests\Generator\Operands::tuple(new \Tests\Generator\MySql\Statement\Table\Column\DefaultLiteralGenerator(new \Tests\Generator\MySql\Statement\Table\Column\Value\CurrentTimestampGenerator()));
        $columns = new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(new ColumnNameGenerator($release), new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator($types, $attributes));
        $requests = Generators::map(static fn (ColumnDefinition $column): CreateTable => new CreateTable($column->name->table ?? new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target')), [$column]), $columns);
        $this->forAll(new AnalyzedStatementGenerator($release, $requests))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $operation) use ($release): void {
            self::assertInstanceOf(CreateTable::class, $operation);
            $database = $operation->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), [], [$database]))->connect(database: $database);
            $session->text = 'generated column attributes without SQL';
            try {
                (new Execution($session))->statement($operation);
                self::fail('The clock default cannot initialize a column with a different precision.');
            } catch (SqlError $error) {
                self::assertSame(1067, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema($database)?->tables);
            self::assertSame('generated column attributes without SQL', $session->text);
        });
    }

    /**
     * A fresh table must contain the generated decoded name after the semantic operation executes.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCreatedColumnRetainsItsName(GrammarRelease $release): void
    {
        $this->forAll(new AnalyzedStatementGenerator($release, new CreateTableGenerator(new ColumnNameGenerator($release))))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $operation) use ($release): void {
            $database = ($operation instanceof CreateTable ? $operation->name->schema?->value : null) ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);

            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation));

            self::assertInstanceOf(Completion::class, $reply);
            self::assertInstanceOf(CreateTable::class, $operation);
            $definition = $operation->elements[0];
            self::assertInstanceOf(ColumnDefinition::class, $definition);
            $schema = $session->instance->dictionary->schema($database);
            self::assertNotNull($schema);
            $table = $schema->table($operation->name->name->value);
            self::assertNotNull($table);
            self::assertCount(1, $table->definition->columns);
            self::assertSame($definition->name->column->value, $table->definition->columns[0]->name);
        });
    }

    /**
     * Contradictory requests remain executable values and fail with their SQL condition.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesDuplicateColumnsFailWithoutInstallingATable(GrammarRelease $release): void
    {
        $requests = new AnalyzedStatementGenerator($release, new CreateTableGenerator(new ColumnNameGenerator($release), duplicateColumns: true, ifNotExists: true));
        $this->forAll($requests)->then(static function (\SqlSemantics\Statement\AnalyzedStatement $operation) use ($release): void {
            $database = ($operation instanceof CreateTable ? $operation->name->schema?->value : null) ?? 'pbt';
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
        $sequences = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $operation): array {
            $create = $operation;
            if (!$create instanceof CreateTable || !$create->elements[0] instanceof ColumnDefinition) {
                throw new LogicException('The creation generator supplies a table and its column.');
            }
            $name = $create->elements[0]->name;
            $duplicate = new ColumnDefinition(new ColumnName($name->column, $name->table, $name->dot), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
            return [$operation, (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext($operation->profile(), [new \SqlSemantics\Statement\Identifier\Name('pbt')], session: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults($operation->profile()->grammar)), new CreateTable($create->name, [$create->elements[0], $duplicate], ifNotExists: true), new \SqlSemantics\Statement\Source\SourceMap())];
        }, new AnalyzedStatementGenerator($release, new CreateTableGenerator(new ColumnNameGenerator($release))));
        $this->forAll($sequences)->then(static function (array $sequence) use ($release): void {
            [$prerequisite, $request] = $sequence;
            self::assertInstanceOf(MySqlStatement::class, $prerequisite);
            self::assertInstanceOf(MySqlStatement::class, $request);
            $database = ($prerequisite instanceof CreateTable ? $prerequisite->name->schema?->value : null) ?? 'pbt';
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
            self::assertSame($request, $prepared->statement);
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

    /**
     * The generated KEY and INDEX spellings install the same declared key parts.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedIndexKeywordsInstallTheDeclaredIndex(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator::tables($release))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $operation) use ($release): void {
            self::assertInstanceOf(CreateTable::class, $operation);
            $index = $operation->elements[1];
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class, $index);
            $database = $operation->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), databases: [$database]))->connect(database: $database);
            $session->text = 'Generated index definition without SQL';
            self::assertInstanceOf(Completion::class, (new Execution($session))->statement($operation));
            $table = $session->instance->dictionary->table($database, $operation->name->name->value);
            self::assertNotNull($table);
            self::assertCount(1, $table->definition->keys);
            self::assertSame($index->name?->column->value, $table->definition->keys[0]->name);
            self::assertSame([0], $table->definition->keys[0]->columns);
            self::assertSame('Generated index definition without SQL', $session->text);
        });
    }
}
