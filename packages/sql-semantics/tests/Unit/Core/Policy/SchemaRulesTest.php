<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaFacade::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SchemaRulesTest extends TestCase
{
    public function testColumnNodesHonorsAnInjectedPolicy(): void
    {
        $node = new Node('application-column', 0, []);
        $rules = self::createStub(\SqlSemantics\Core\Policy\SchemaRules::class);
        $rules->method('columnNodes')->willReturn([[$node, []]]);
        $platform = self::createStub(\SqlSemantics\Core\Platform::class);
        $platform->method('schema')->willReturn($rules);
        $dialect = self::createStub(Dialect::class);
        $dialect->method('platform')->willReturn($platform);
        $reader = new \SqlSemantics\Core\Ast\SchemaReader(new \SqlSemantics\Core\Ast\Identifiers($dialect), 'application', new \SqlSemantics\Core\Analysis\ValueReader(new \SqlSemantics\Core\Analysis\Vocabulary([])));
        self::assertSame([[$node, []]], $reader->columnNodes(new Node('application-table', 0, [])));
    }
    public function testValidateAcceptsOrdinaryDeclarations(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertCount(1, $schema->tables);
    }
    public function testPrimaryNotNullPromotesAnIntegerKey(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertSame(Nullability::NotNull, $schema->tables[0]->columns[0]->nullability);
    }
    public function testSchemaNodeRetainsOriginalDeclaration(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertStringContainsString('CREATE TABLE', \SqlSemantics\Statement\Writer::render($schema->tables[0]->source));
    }
    public function testTableKeyDistinguishesNames(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE first (id INT)', 'CREATE TABLE second (id INT)');
        self::assertNotSame(PostgreSqlDialect::PostgreSql->platform()->schema()->tableKey($schema->tables[0]), PostgreSqlDialect::PostgreSql->platform()->schema()->tableKey($schema->tables[1]));
    }
    public function testQualifyPreservesExplicitNamespaces(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE app.items (id INT)');
        self::assertSame('app', $schema->tables[0]->schema);
    }

    public function testOptionsRetainsStructuredTableChoices(): void
    {
        $state = (new SchemaFacade(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (id TEXT PRIMARY KEY) STRICT');
        self::assertNotEmpty($state->tables[0]->options);
    }

    public function testPrimaryOptionsNotNullAccountsForTablePolicy(): void
    {
        $dialect = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite;
        $source = $dialect->platform()->parser()->parse('CREATE TABLE t (id TEXT PRIMARY KEY) STRICT');
        self::assertSame(true, $dialect->platform()->schema()->primaryOptionsNotNull($source));
    }

}
