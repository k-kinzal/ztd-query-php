<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SchemaRulesTest extends TestCase
{
    public function testValidateAcceptsOrdinaryDeclarations(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertCount(1, $schema->declarations);
    }

    public function testColumnNodesPreserveOrder(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertSame(['id', 'label'], array_column($schema->declarations[0]->columns, 'name'));
    }

    public function testPrimaryNotNullPromotesAnIntegerKey(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertSame(Nullability::NotNull, $schema->declarations[0]->columns[0]->nullability);
    }

    public function testSchemaNodeRetainsOriginalDeclaration(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertStringContainsString('CREATE TABLE', \SqlSemantics\Statement\Writer::render($schema->declarations[0]->source));
    }

    public function testTableKeyDistinguishesNames(): void
    {
        $statements = (new Semantics(Dialect::Sqlite))->analyzeAll('CREATE TABLE first (id INT); CREATE TABLE second (id INT)', []);
        self::assertNotSame(Dialect::Sqlite->platform()->schema()->tableKey($statements[0]->resolution?->declarations[0] ?? self::fail('first')), Dialect::Sqlite->platform()->schema()->tableKey($statements[1]->resolution?->declarations[0] ?? self::fail('second')));
    }

    public function testQualifyPreservesExplicitNamespaces(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE app.items (id INT)', []));
        self::assertSame('app', $schema->declarations[0]->schema);
    }

    public function testOptionsRetainsStructuredTableChoices(): void
    {
        $state = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id TEXT PRIMARY KEY) STRICT', []));
        self::assertNotEmpty($state->declarations[0]->options);
    }

    public function testPrimaryOptionsNotNullAccountsForTablePolicy(): void
    {
        $dialect = Dialect::Sqlite;
        $source = $dialect->platform()->parser()->parse('CREATE TABLE t (id TEXT PRIMARY KEY) STRICT');
        self::assertSame(true, $dialect->platform()->schema()->primaryOptionsNotNull($source));
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE TABLE t (id TEXT PRIMARY KEY)', 'maybe-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE TABLE t (id TEXT PRIMARY KEY) STRICT', 'not-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE TABLE t (id TEXT PRIMARY KEY) WITHOUT ROWID', 'not-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE TABLE t (id INTEGER PRIMARY KEY DESC)', 'maybe-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE TABLE t (id INTEGER, PRIMARY KEY (id DESC))', 'not-null'])]
    public function testPrimaryNotNullRespectsTableOptionsAndInlineDescendingKeys(string $sql, string $expected): void
    {
        $state = Resolved::of((new Semantics(Dialect::Sqlite))->analyze($sql, []));
        self::assertSame($expected, $state->declarations[0]->columns[0]->nullability->value);
    }

}
