<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Expression::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Statement\Statement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Statement\Writer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Statement\Element::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\AnalysisException::class)]
final class PlatformTest extends TestCase
{
    public function testParserPreservesTheSelectedVersion(): void
    {
        $parser = Dialect::Sqlite->platform()->parser();
        self::assertNotSame('', $parser->version());
        self::assertSame('SELECT 1', $parser->parse('SELECT 1')->toString());
    }

    public function testDefaultSchemaUsesTheLanguageNamespace(): void
    {
        self::assertSame('main', Dialect::Sqlite->platform()->defaultSchema());
    }

    public function testStatementNamesIdentifyTheParserRoot(): void
    {
        self::assertSame(Dialect::Sqlite->platform()->statementNames()[0], Dialect::Sqlite->platform()->parser()->parse('SELECT 1')->name);
    }

    public function testSyntaxRecognizesSelectBody(): void
    {
        $tree = Dialect::Sqlite->platform()->parser()->parse('SELECT 1');
        self::assertNotEmpty(\SqlSemantics\Core\Ast\Tree::outer($tree, Dialect::Sqlite->platform()->syntax()->nodes('selectBody')));
    }

    public function testNamesDecodeQuotedIdentifiers(): void
    {
        self::assertSame('Mixed', Dialect::Sqlite->platform()->names()->name(new Token(1, 'ID', '"Mixed"', 0)));
    }

    public function testTypesRetainDialectIdentity(): void
    {
        self::assertSame(Dialect::Sqlite, Dialect::Sqlite->platform()->types()->boolean()->dialect);
    }

    public function testSchemaKeepsDeclarations(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertCount(2, $schema->tables[0]->columns);
    }

    public function testQueryKeepsProjectionOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        $bound = (new Binder($schema))->bind('SELECT label, id FROM items');
        self::assertSame(['label', 'id'], array_column($bound->outputs, 'name'));
    }
    public function testValuesReconstructsUsingTheParserRelease(): void
    {
        $platform = Dialect::Sqlite->platform();
        $parser = $platform->parser();
        $value = $platform->values($parser->version())->read($parser->parse('SELECT 42'));
        self::assertSame('SELECT 42', (new \SqlSemantics\Statement\Statement($value))->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'CREATE VIRTUAL TABLE docs USING fts5(title, body)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'CREATE TRIGGER tr AFTER INSERT ON t BEGIN UPDATE u SET n = n + 1 WHERE id = new.id; DELETE FROM log; END'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'INSERT INTO t (id) VALUES (1) ON CONFLICT(id) DO UPDATE SET id = excluded.id RETURNING id'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'SELECT CASE WHEN n IS NULL THEN 0 ELSE n END, ROW_NUMBER() OVER (ORDER BY n) FROM t LEFT JOIN u USING (id)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'ALTER TABLE t ADD name TEXT REFERENCES other(id) ON UPDATE CASCADE'])]
    public function testAnalyzeRoundTripsCompleteStatements(Dialect $dialect, string $sql): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics($dialect))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter($dialect->platform()->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }

}
