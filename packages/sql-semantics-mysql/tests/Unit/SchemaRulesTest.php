<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use Tests\Contract\Resolved;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
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
        $schema = Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertCount(1, $schema->declarations);
    }

    public function testColumnNodesPreserveOrder(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertSame(['id', 'label'], array_column($schema->declarations[0]->columns, 'name'));
    }

    public function testPrimaryNotNullPromotesAnIntegerKey(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertSame(Nullability::NotNull, $schema->declarations[0]->columns[0]->nullability);
    }

    public function testSchemaNodeRetainsOriginalDeclaration(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertStringContainsString('CREATE TABLE', \SqlSemantics\Statement\Writer::render($schema->declarations[0]->source));
    }

    public function testTableKeyDistinguishesNames(): void
    {
        $statements = (new Semantics(Dialect::MySql))->analyzeAll('CREATE TABLE first (id INT); CREATE TABLE second (id INT)', []);
        self::assertNotSame(Dialect::MySql->platform()->schema()->tableKey($statements[0]->resolution?->declarations[0] ?? self::fail('first')), Dialect::MySql->platform()->schema()->tableKey($statements[1]->resolution?->declarations[0] ?? self::fail('second')));
    }

    public function testQualifyPreservesExplicitNamespaces(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE app.items (id INT)', []));
        self::assertSame('app', $schema->declarations[0]->schema);
    }

    public function testOptionsRetainsStructuredTableChoices(): void
    {
        $state = Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (id INT) ENGINE=InnoDB', []));
        self::assertNotEmpty($state->declarations[0]->options);
    }

    public function testPrimaryOptionsNotNullAccountsForTablePolicy(): void
    {
        $dialect = Dialect::MySql;
        $source = $dialect->platform()->parser()->parse('CREATE TABLE t (id INT) ENGINE=InnoDB');
        self::assertSame(false, $dialect->platform()->schema()->primaryOptionsNotNull($source));
    }

}
