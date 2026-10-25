<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertStatementGenerator;

/**
 * Executes generated INSERT values at the same boundary used by analyzed SQL.
 */
#[CoversNothing]
#[Large]
final class InsertExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Missing state produces the specific SQL error without hiding implementation failures.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesAbsentTableIsAnSqlError(GrammarRelease $release): void
    {
        $this->forAll(new InsertStatementGenerator($release))->withMaxSize(10)->then(static function (InsertStatement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            try {
                (new Execution($session))->statement($statement);
                self::fail('An INSERT into an absent table must fail.');
            } catch (SqlError $error) {
                self::assertSame(QueryError::NoSuchTable, $error->error);
                self::assertSame("Table 'pbt.{$statement->target->name->name->value}' doesn't exist", $error->getMessage());
            }
        });
    }

    /**
     * Generated declarations and dependent inserts retain their relationship through shrinking.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCreatedTableStoresConvertedValues(GrammarRelease $release): void
    {
        $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci');
        $sequences = \Eris\Generators::map(static function (array $data) use ($release): array {
            $create = $data['create'];
            $values = $data['values'];
            $comparison = $data['comparison'];
            if (!$create instanceof \SqlSemantics\Statement\Operation || !$values instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClause || !$comparison instanceof \SqlSemantics\Statement\Identifier\Comparison) {
                throw new LogicException('The sequence requires a generated CREATE TABLE and VALUES clause.');
            }
            $table = $create->declarations()[0];
            $resolution = new \SqlSemantics\Statement\Reference\Table\DeclaredTable($table);
            $column = $table->columns[0];
            $written = $comparison === \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive ? new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name(strtoupper($table->name->name->value)), $table->name->schema) : $table->name;
            $target = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertTarget($written, $resolution, [new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn($column->name, $resolution, $column)], declarationSchema: $create->context->declarationSchema, comparison: $comparison);
            return [$create, new InsertStatement(new \SqlSemantics\Contract\LanguageProfile($release), $target, $values, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'))];
        }, \Eris\Generators::associative([
            'create' => new \Tests\Generator\Semantics\Statement\OperationGenerator($release),
            'comparison' => new \Tests\Generator\Semantics\Statement\Identifier\ComparisonGenerator(),
            'values' => new \Tests\Generator\MySql\Statement\Dml\Insert\ValuesClauseGenerator(new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator($collation, \Eris\Generators::map(static fn (int $number): string => (string) $number, \Eris\Generators::int()), \Eris\Generators::oneOf(\Eris\Generators::constant(null), new \Tests\Generator\Semantics\Statement\Source\SpanGenerator()))),
        ]));
        $this->forAll($sequences)->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            [$create, $insert] = $sequence;
            self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $create);
            self::assertInstanceOf(InsertStatement::class, $insert);
            $database = $create->context->declarationSchema->value;
            $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);
            $execution = new Execution($session);
            $execution->statement($create);
            $reply = $execution->statement($insert);
            self::assertNotNull($insert->target->resolvedName);
            $table = $session->instance->dictionary->table($database, $insert->target->resolvedName->name->value);
            self::assertNotNull($table);
            $expected = [];
            foreach ($insert->values->rows as $row) {
                self::assertCount(1, $row);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral::class, $row[0]);
                $expected[] = [(int) $row[0]->value()];
            }
            self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
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
