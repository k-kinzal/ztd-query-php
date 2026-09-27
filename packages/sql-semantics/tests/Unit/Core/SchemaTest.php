<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;

#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaFacade::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
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
final class SchemaTest extends TestCase
{
    public function testRetainsTheDialectAndAllDeclarations(): void
    {
        $builder = new SchemaFacade(PostgreSqlDialect::PostgreSql);
        $schema = $builder->analyze('CREATE TABLE a (id INTEGER); CREATE TABLE b (id INTEGER)');
        self::assertSame(PostgreSqlDialect::PostgreSql, $schema->dialect);
        self::assertSame(['a', 'b'], array_column($schema->tables, 'name'));
    }
    public function testWithTablesLeavesThePreviousStateIntact(): void
    {
        $state = (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (id INT)');
        $empty = $state->withTables();
        self::assertCount(1, $state->tables);
        self::assertSame([], $empty->tables);
        self::assertSame($state->grammarVersion, $empty->grammarVersion);
        self::assertSame($state->tables, $empty->withTables(...$state->tables)->tables);
    }
}
