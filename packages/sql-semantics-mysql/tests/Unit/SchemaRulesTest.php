<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\MySql\Dialect;

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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SchemaRulesTest extends TestCase
{
    public function testValidateAcceptsOrdinaryDeclarations(): void
    {
        $schema = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertCount(1, $schema->tables);
    }

    public function testColumnNodesPreserveOrder(): void
    {
        $schema = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertSame(['id', 'label'], array_column($schema->tables[0]->columns, 'name'));
    }

    public function testPrimaryNotNullPromotesAnIntegerKey(): void
    {
        $schema = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertSame(Nullability::NotNull, $schema->tables[0]->columns[0]->nullability);
    }

    public function testSchemaNodeRetainsOriginalDeclaration(): void
    {
        $schema = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertStringContainsString('CREATE TABLE', \SqlSemantics\Statement\Writer::render($schema->tables[0]->source));
    }

    public function testTableKeyDistinguishesNames(): void
    {
        $schema = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE first (id INT)', 'CREATE TABLE second (id INT)');
        self::assertNotSame(Dialect::MySql->platform()->schema()->tableKey($schema->tables[0]), Dialect::MySql->platform()->schema()->tableKey($schema->tables[1]));
    }

    public function testQualifyPreservesExplicitNamespaces(): void
    {
        $schema = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE app.items (id INT)');
        self::assertSame('app', $schema->tables[0]->schema);
    }

    public function testOptionsRetainsStructuredTableChoices(): void
    {
        $state = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE t (id INT) ENGINE=InnoDB');
        self::assertNotEmpty($state->tables[0]->options);
    }

    public function testPrimaryOptionsNotNullAccountsForTablePolicy(): void
    {
        $dialect = Dialect::MySql;
        $source = $dialect->platform()->parser()->parse('CREATE TABLE t (id INT) ENGINE=InnoDB');
        self::assertSame(false, $dialect->platform()->schema()->primaryOptionsNotNull($source));
    }

}
