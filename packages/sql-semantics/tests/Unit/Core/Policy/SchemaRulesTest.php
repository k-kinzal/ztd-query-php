<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
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
        $schema = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertCount(1, $schema->declarations);
    }
    public function testPrimaryNotNullPromotesAnIntegerKey(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertSame(Nullability::NotNull, $schema->declarations[0]->columns[0]->nullability);
    }
    public function testSchemaNodeRetainsOriginalDeclaration(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertStringContainsString('CREATE TABLE', \SqlSemantics\Statement\Writer::render($schema->declarations[0]->source));
    }
    public function testTableKeyDistinguishesNames(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $statements = (new Semantics(PostgreSqlDialect::PostgreSql))->analyzeAll('CREATE TABLE first (id INT); CREATE TABLE second (id INT)', []);
        self::assertNotSame(PostgreSqlDialect::PostgreSql->platform()->schema()->tableKey($statements[0]->resolution?->declarations[0] ?? self::fail('first')), PostgreSqlDialect::PostgreSql->platform()->schema()->tableKey($statements[1]->resolution?->declarations[0] ?? self::fail('second')));
    }
    public function testQualifyPreservesExplicitNamespaces(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\SchemaRules $rules): \SqlSemantics\Core\Policy\SchemaRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->schema()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->schema())::class);
        $schema = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE app.items (id INT)', []));
        self::assertSame('app', $schema->declarations[0]->schema);
    }

    public function testOptionsRetainsStructuredTableChoices(): void
    {
        $state = Resolved::of((new Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (id TEXT PRIMARY KEY) STRICT', []));
        self::assertNotEmpty($state->declarations[0]->options);
    }

    public function testPrimaryOptionsNotNullAccountsForTablePolicy(): void
    {
        $dialect = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite;
        $source = $dialect->platform()->parser()->parse('CREATE TABLE t (id TEXT PRIMARY KEY) STRICT');
        self::assertSame(true, $dialect->platform()->schema()->primaryOptionsNotNull($source));
    }


    public function testNullabilityPreservesExplicitNotNull(): void
    {
        $table = (new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t(a INT NOT NULL)', [])->resolution?->declarations[0];
        self::assertSame(Nullability::NotNull, $table?->columns[0]->nullability);
    }
    public function testDefaultValueSeparatesTheExpression(): void
    {
        $value = (new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t(a INT DEFAULT 42)', [])->resolution?->declarations[0]->columns[0]->defaultValue;
        self::assertNotNull($value);
        self::assertSame('42', \SqlSemantics\Statement\Writer::render($value));
    }
    public function testKeyColumnsKeepOrder(): void
    {
        $table = (new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t(a INT, b INT, PRIMARY KEY(b,a))', [])->resolution?->declarations[0];
        self::assertSame(['b', 'a'], $table?->constraints[0]->columns);
    }
    public function testImplicitSchemasDefinesUnqualifiedLookupOrder(): void
    {
        self::assertSame([], (new Semantics(PostgreSqlDialect::PostgreSql))->language()->dialect->platform()->schema()->implicitSchemas());
    }

}
