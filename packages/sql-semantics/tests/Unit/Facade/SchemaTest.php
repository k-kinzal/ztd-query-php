<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Writer;

#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Core\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Core\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Core\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Core\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Core\Model\Join::class)]
#[CoversClass(\SqlSemantics\Core\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Core\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Core\Model\BoundSelect::class)]
#[CoversClass(\SqlSemantics\Core\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
#[CoversClass(\SqlSemantics\Core\Analysis\SchemaAnalyzer::class)]
#[CoversClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnProperties::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaChanges::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnGeneration::class)]
#[CoversClass(\SqlSemantics\Core\Schema\Invariant::class)]
#[CoversClass(Schema::class)]
#[CoversClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[CoversClass(Writer::class)]
#[CoversClass(\SqlSemantics\Statement\Assertion::class)]
final class SchemaTest extends TestCase
{
    #[TestWith([MySqlDialect::MySql, 'CREATE TABLE t (id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(30) COLLATE utf8mb4_bin, doubled INT GENERATED ALWAYS AS (id * 2) STORED) ENGINE=InnoDB', 'COLLATE utf8mb4_bin'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'CREATE TABLE t (id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, label TEXT COLLATE "C", doubled INT GENERATED ALWAYS AS (id * 2) STORED) WITH (fillfactor=70)', 'COLLATE "C"'])]
    #[TestWith([SqliteDialect::Sqlite, 'CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT COLLATE NOCASE, doubled INT AS (id * 2) STORED) STRICT', 'COLLATE NOCASE'])]
    public function testAnalyzePreservesStateProperties(Dialect $dialect, string $sql, string $collation): void
    {
        $state = (new Schema($dialect))->analyze('DROP TABLE IF EXISTS t; ' . $sql . ';');
        $table = $state->tables[0];
        self::assertSame(['id', 'label', 'doubled'], array_column($table->columns, 'name'));
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
        self::assertNotNull($table->columns[1]->collation);
        self::assertSame($collation, Writer::render($table->columns[1]->collation));
        self::assertNotNull($table->columns[2]->generation);
        self::assertSame('stored', $table->columns[2]->generation->kind->value);
        self::assertNotNull($table->columns[2]->generation->expression);
        self::assertSame('id * 2', Writer::render($table->columns[2]->generation->expression));
        self::assertNotEmpty($table->options);
        self::assertStringNotContainsString('SqlParser', serialize($state));
        self::assertSame(['id', 'label', 'doubled'], array_column((new Binder($state))->bind('SELECT * FROM t')->outputs, 'name'));
    }

    public function testAnalyzeIsIndependentAcrossCalls(): void
    {
        $reader = new Schema(PostgreSqlDialect::PostgreSql, 'app');
        $first = $reader->analyze('CREATE TABLE t (id INT)');
        self::assertSame('app', $first->tables[0]->schema);
        self::assertSame([], $reader->analyze()->tables);
        self::assertCount(1, $first->tables);
    }

    public function testAnalyzeDistinguishesInvalidSyntaxFromStateConflicts(): void
    {
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Schema(SqliteDialect::Sqlite))->analyze('CREATE TABLE');
    }

    public function testAnalyzeRejectsDuplicateStateIdentities(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate table');
        (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (id INT)', 'CREATE TABLE t (id INT)');
    }

    public function testAnalyzePreservesEmptyQuotedNames(): void
    {
        $state = (new Schema(SqliteDialect::Sqlite))->analyze('CREATE TABLE "" ("" INTEGER PRIMARY KEY)');
        self::assertSame('', $state->tables[0]->name);
        self::assertSame('', $state->tables[0]->columns[0]->name);
        self::assertSame([''], $state->tables[0]->constraints[0]->columns);
    }
    public function testAnalyzePreservesAnExplicitZeroColumnTable(): void
    {
        $table = (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE empty_table ()')->tables[0];
        self::assertSame('empty_table', $table->name);
        self::assertSame([], $table->columns);
    }
}
