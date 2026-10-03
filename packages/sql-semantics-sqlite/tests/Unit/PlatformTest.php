<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Parameters;
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Language::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Composition\Composition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Composition\Operands::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Analysis\LeafReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Builder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Statement\Statement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Statement\Writer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Statement\Element::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
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

    public function testSearchPathDefaultsToTheServersSchemas(): void
    {
        self::assertSame(['main'], Dialect::Sqlite->platform()->searchPath());
    }

    public function testSearchPathStartsWithMainAndListsAttachedDatabases(): void
    {
        self::assertSame(['main', 'aux'], Dialect::Sqlite->platform()->searchPath(new \SqlSemantics\Core\SearchPath('main', 'aux')));
    }

    public function testStatementNamesIdentifyTheParserRoot(): void
    {
        self::assertSame(Dialect::Sqlite->platform()->statementNames()[0], Dialect::Sqlite->platform()->parser()->parse('SELECT 1')->name);
    }


    public function testNamesDecodeQuotedIdentifiers(): void
    {
        self::assertSame('Mixed', Dialect::Sqlite->platform()->names()->name(new Token(1, 'ID', '"Mixed"', 0)));
    }


    public function testSchemaKeepsDeclarations(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertCount(2, $schema->declarations[0]->columns);
    }

    public function testSyntaxRecognizesTheCreateTableDeclaration(): void
    {
        $tree = Dialect::Sqlite->platform()->parser()->parse('CREATE TABLE t (id INTEGER)');
        self::assertNotEmpty(\SqlSemantics\Core\Ast\Tree::outer($tree, Dialect::Sqlite->platform()->syntax()->nodes('createTable')));
    }

    public function testTypesReadDeclaredTypes(): void
    {
        self::assertTrue(Dialect::Sqlite->platform()->types()->supports(\SqlSemantics\Statement\Declaration\Builtin::Integer));
    }

    public function testParserReadsNamedPlaceholdersOnRequest(): void
    {
        self::assertSame('VARIABLE', Dialect::Sqlite->platform()->parser(null, null, Parameters::Named)->tokenize('SELECT :id')[1]->name);
    }


    public function testRelationsNameTheTablePositionsOfTheGrammar(): void
    {
        $rules = Dialect::Sqlite->platform()->relations();
        self::assertNotEmpty($rules->nameSymbols);
        self::assertNotEmpty($rules->declarations);
        self::assertNotEmpty($rules->drops);
        self::assertNotEmpty($rules->commonTableExpressions);
        self::assertNotEmpty($rules->withClauses);
        self::assertSame(\SqlSemantics\Core\Policy\WithVisibility::All, $rules->visibility);
        self::assertSame(\SqlSemantics\Core\Policy\WithVisibility::All, $rules->recursiveVisibility);
    }

    public function testRelationsScopeCommonTableExpressionsAsTheServerDoes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        $kinds = static fn (string $sql): string => implode(' ', array_map(static fn (\SqlSemantics\Statement\Reference $reference): string => implode('.', $reference->name) . ':' . $reference->kind->name, $semantics->analyze($sql, [$users])->resolution->references ?? []));
        self::assertSame('users:CommonTableExpression users:CommonTableExpression users:CommonTableExpression', $kinds('WITH users AS (SELECT * FROM users WHERE id > 1) SELECT * FROM users'));
        self::assertSame('users:CommonTableExpression users:Dependency users:CommonTableExpression', $kinds('WITH users AS (SELECT 9 AS id) DELETE FROM users WHERE id IN (SELECT id FROM users)'));
    }

    public function testBuilderComposesThisDatabasesValues(): void
    {
        $builder = Dialect::Sqlite->platform()->builder(new Language(Dialect::Sqlite));
        self::assertInstanceOf(\SqlSemantics\Platform\Sqlite\Builder::class, $builder);
        self::assertSame('a = 1', \SqlSemantics\Statement\Writer::render($builder->compare($builder->column('a'), '=', $builder->integer(1))));
    }

    public function testValuesReconstructsUsingTheParserRelease(): void
    {
        $platform = Dialect::Sqlite->platform();
        $parser = $platform->parser();
        $value = $platform->values($parser->version())->read($parser->parse('SELECT 42'));
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $value);
        self::assertSame('SELECT 42', (new \SqlSemantics\Statement\Statement($value))->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'CREATE VIRTUAL TABLE docs USING fts5(title, body)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'CREATE TRIGGER tr AFTER INSERT ON t BEGIN UPDATE u SET n = n + 1 WHERE id = new.id; DELETE FROM log; END'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'INSERT INTO t (id) VALUES (1) ON CONFLICT(id) DO UPDATE SET id = excluded.id RETURNING id'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'SELECT CASE WHEN n IS NULL THEN 0 ELSE n END, ROW_NUMBER() OVER (ORDER BY n) FROM t LEFT JOIN u USING (id)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'ALTER TABLE t ADD name TEXT REFERENCES other(id) ON UPDATE CASCADE'])]
    public function testAnalyzeRoundTripsCompleteStatements(Dialect $dialect, string $sql): void
    {
        $statement = (new Semantics($dialect))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter($dialect->platform()->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }


    public function testLiteralsSuppliesTheDecoder(): void
    {
        $language = new Language(Dialect::Sqlite);
        $tokens = array_values(array_filter($language->parser()->tokenize("'x'"), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        self::assertSame('x', $language->dialect->platform()->literals($language)->decode($tokens)->value());
    }


    public function testOperationsReturnsImmutableMeaning(): void
    {
        $language = new Language(Dialect::Sqlite);
        $platform = new \SqlSemantics\Platform\Sqlite\Platform();
        $catalog = $platform->catalog($platform->searchPath(), false);
        $operation = $platform->operations($language)->read($language->parser()->parse('BEGIN'), $catalog);
        self::assertInstanceOf(\SqlSemantics\Statement\Transaction\Begin::class, $operation);
        self::assertTrue((new \SqlSemantics\Statement\SemanticGraph())->isSemanticOperation($operation));
    }

    public function testCatalogRetainsExactDeclarationIdentity(): void
    {
        $platform = new \SqlSemantics\Platform\Sqlite\Platform();
        $table = new \SqlSemantics\Statement\Schema\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('bar')));
        $catalog = $platform->catalog($platform->searchPath(), true, $table);
        self::assertSame([$table], $catalog->tables);
        self::assertSame([$table], $catalog->matchingTables($table->name));
        self::assertTrue($catalog->complete);
    }
}
