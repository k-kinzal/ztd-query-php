<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Resolved;

#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
#[CoversClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnProperties::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\ColumnGeneration::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[CoversClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[CoversClass(Writer::class)]
#[CoversClass(\SqlSemantics\Statement\Assertion::class)]
final class InvariantTest extends TestCase
{
    public function testMembersPreservesDeclarationOrder(): void
    {
        $table = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (z INT, a TEXT)', []))->declarations[0];
        self::assertSame(['z', 'a'], array_column($table->columns, 'name'));
    }

    public function testNamesPreservesCompositeKeyOrder(): void
    {
        $table = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (z INT, a INT, PRIMARY KEY (a, z))', []))->declarations[0];
        self::assertSame(['a', 'z'], $table->constraints[0]->columns);
    }

    public function testElementsPreservesImmutableSemanticGraphs(): void
    {
        $table = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (a INT DEFAULT 1)', []))->declarations[0];
        self::assertStringNotContainsString('SqlParser', serialize($table));
        self::assertTrue((new \SqlSemantics\Statement\ImmutableGraph())->containsOnlyImmutableValues($table->source));
    }

    public function testEnsureAcceptsACompleteState(): void
    {
        $state = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY)', []));
        self::assertSame(Nullability::NotNull, $state->declarations[0]->columns[0]->nullability);
    }
    public function testTablePreservesAValidCompositeForeignKey(): void
    {
        $semantics = new Semantics(PostgreSqlDialect::PostgreSql);
        $state = Resolved::of($semantics->analyze('CREATE TABLE t (a INT, b INT, FOREIGN KEY (b, a) REFERENCES parent (y, x))', [$semantics->analyze('CREATE TABLE parent (x INT, y INT)')]));
        self::assertSame(['b', 'a'], $state->declarations[0]->constraints[0]->columns);
        self::assertSame(['y', 'x'], $state->declarations[0]->constraints[0]->referencedColumns);
    }
}
