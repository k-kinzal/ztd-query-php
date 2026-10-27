<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use Eris\TestTrait;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertRowsGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\ValuesKeywordGenerator;
use Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;
use Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Executes generated INSERT forms directly, with and without generated dependencies.
 */
#[CoversNothing]
#[Large]
final class InsertExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Missing catalog state is a SQL error; model and implementation failures escape.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesAbsentTableIsAnSqlError(GrammarRelease $release): void
    {
        $rows = Generators::vector(1, new InsertedRowGenerator(Generators::vector(1, new StringLiteralGenerator(Collation::known('utf8mb4_unicode_ci')))));
        $inserts = new InsertRowsGenerator(
            new InsertIntoGenerator(Generators::constant(false), Generators::constant(null), Generators::constant(false), new WriteTargetGenerator(new QualifiedNameGenerator(new NameGenerator())), intoWritten: Generators::bool()),
            $rows,
            keyword: new ValuesKeywordGenerator(),
        );
        $this->forAll(new AnalyzedStatementGenerator($release, $inserts))->withMaxSize(10)->then(static function ($statement) use ($release): void {
            self::assertInstanceOf(InsertRows::class, $statement);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated INSERT without SQL text';
            try {
                (new Execution($session))->statement($statement);
                self::fail('An INSERT into an absent table must fail.');
            } catch (SqlError $error) {
                self::assertSame(QueryError::NoSuchTable, $error->error);
                self::assertSame("Table 'pbt.{$statement->into->table->name->name->value}' doesn't exist", $error->getMessage());
            }
        });
    }

    /**
     * Eris shrinks declarations and row values together, rebuilding their references
     * before execution. The callback consumes both generated statements unchanged.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCreatedTableStoresConvertedValues(GrammarRelease $release): void
    {
        $strings = new StringLiteralGenerator(Collation::known('utf8mb4_unicode_ci'), Generators::map(static fn (int $number): string => (string) $number, Generators::choose(-100000, 100000)));
        $rows = Generators::vector(3, new InsertedRowGenerator(Generators::vector(1, $strings)));
        $sequences = Generators::map(static function (array $data) use ($release): array {
            [$create, $rows, $keyword] = $data;
            $database = $create->name->schema ?? new Name('pbt');
            $context = new AnalysisContext(new LanguageProfile($release), [$database], session: new Settings(Collation::known('utf8mb4_unicode_ci')));
            $publication = new Publication();
            $definition = $publication->publish($context, $create);
            $insert = new InsertRows(new InsertInto(false, null, false, new WriteTarget($create->name)), $rows, keyword: $keyword);
            $context = new AnalysisContext($context->profile, [$database], $definition->declarations(), session: $context->session);
            return [$definition, $publication->publish($context, $insert)];
        }, \Tests\Generator\Operands::tuple(new CreateTableGenerator(new ColumnNameGenerator($release)), $rows, new ValuesKeywordGenerator()));
        $this->forAll($sequences)->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            [$create, $insert] = $sequence;
            self::assertInstanceOf(CreateTable::class, $create);
            self::assertInstanceOf(InsertRows::class, $insert);
            $database = $create->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);
            $session->text = 'Generated CREATE and INSERT without SQL text';
            $execution = new Execution($session);
            $execution->statement($create);
            $reply = $execution->statement($insert);
            $table = $session->instance->dictionary->table($database, $insert->into->table->name->name->value);
            self::assertNotNull($table);
            $expected = [];
            foreach ($insert->rows as $row) {
                self::assertCount(1, $row->values);
                self::assertInstanceOf(StringLiteral::class, $row->values[0]);
                $expected[] = [(int) $row->values[0]->value()];
            }
            self::assertInstanceOf(Completion::class, $reply);
            self::assertSame($expected, array_values($table->data->rows));
            self::assertSame(count($expected), $reply->affectedRows);
            self::assertSame(0, $reply->warnings);
            self::assertSame(count($expected), $session->variables->rowCount);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
