<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
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
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Language::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Composition\Composition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Composition\Operands::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Analysis\LeafReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Builder::class)]
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
        $parser = Dialect::PostgreSql->platform()->parser();
        self::assertNotSame('', $parser->version());
        self::assertSame('SELECT 1', $parser->parse('SELECT 1')->toString());
    }

    public function testSearchPathDefaultsToTheServersSchemas(): void
    {
        self::assertSame(['public'], Dialect::PostgreSql->platform()->searchPath());
    }

    public function testSearchPathIsTheSessionsSearchPath(): void
    {
        self::assertSame(['app', 'public'], Dialect::PostgreSql->platform()->searchPath(new \SqlSemantics\Core\SearchPath('app', 'public')));
    }

    public function testStatementNamesIdentifyTheParserRoot(): void
    {
        self::assertSame(Dialect::PostgreSql->platform()->statementNames()[0], Dialect::PostgreSql->platform()->parser()->parse('SELECT 1')->name);
    }


    public function testNamesDecodeQuotedIdentifiers(): void
    {
        self::assertSame('Mixed', Dialect::PostgreSql->platform()->names()->name(new Token(1, 'ID', '"Mixed"', 0)));
    }


    public function testSchemaKeepsDeclarations(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertCount(2, $schema->declarations[0]->columns);
    }

    public function testSyntaxRecognizesTheCreateTableDeclaration(): void
    {
        $tree = Dialect::PostgreSql->platform()->parser()->parse('CREATE TABLE t (id INTEGER)');
        self::assertNotEmpty(\SqlSemantics\Core\Ast\Tree::outer($tree, Dialect::PostgreSql->platform()->syntax()->nodes('createTable')));
    }

    public function testTypesReadDeclaredTypes(): void
    {
        self::assertTrue(Dialect::PostgreSql->platform()->types()->supports(\SqlSemantics\Statement\Declaration\Builtin::Integer));
    }

    public function testParserReadsNamedPlaceholdersOnRequest(): void
    {
        self::assertSame('PARAM', Dialect::PostgreSql->platform()->parser(null, null, Parameters::Named)->tokenize('SELECT :id')[1]->name);
    }


    public function testRelationsNameTheTablePositionsOfTheGrammar(): void
    {
        $rules = Dialect::PostgreSql->platform()->relations();
        self::assertNotEmpty($rules->nameSymbols);
        self::assertNotEmpty($rules->declarations);
        self::assertNotEmpty($rules->drops);
        self::assertNotEmpty($rules->commonTableExpressions);
        self::assertNotEmpty($rules->withClauses);
        self::assertSame(\SqlSemantics\Core\Policy\WithVisibility::Preceding, $rules->visibility);
        self::assertSame(\SqlSemantics\Core\Policy\WithVisibility::All, $rules->recursiveVisibility);
    }

    public function testRelationsScopeCommonTableExpressionsAsTheServerDoes(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $users = $semantics->analyze('CREATE TABLE users (id INTEGER)');
        $kinds = static fn (string $sql): string => implode(' ', array_map(static fn (\SqlSemantics\Statement\Reference $reference): string => implode('.', $reference->name) . ':' . $reference->kind->name, $semantics->analyze($sql, [$users])->resolution->references ?? []));
        self::assertSame('users:CommonTableExpression users:Dependency users:CommonTableExpression', $kinds('WITH users AS (SELECT * FROM users WHERE id > 1) SELECT * FROM users'));
        self::assertSame('users:CommonTableExpression users:Dependency users:CommonTableExpression', $kinds('WITH users AS (SELECT 9 AS id) DELETE FROM users WHERE id IN (SELECT id FROM users)'));
    }

    public function testBuilderComposesThisDatabasesValues(): void
    {
        $builder = Dialect::PostgreSql->platform()->builder(new Language(Dialect::PostgreSql));
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Builder::class, $builder);
        self::assertSame('a = 1', \SqlSemantics\Statement\Writer::render($builder->compare($builder->column('a'), '=', $builder->integer(1))));
    }

    public function testValuesReconstructsUsingTheParserRelease(): void
    {
        $platform = Dialect::PostgreSql->platform();
        $parser = $platform->parser();
        $value = $platform->values($parser->version())->read($parser->parse('SELECT 42'));
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $value);
        self::assertSame('SELECT 42', (new \SqlSemantics\Statement\Statement($value))->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'CREATE LANGUAGE lang HANDLER handle_lang'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'DO \'text\' \'text\''])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'MERGE INTO t USING u ON t.id = u.id WHEN MATCHED THEN UPDATE SET v = u.v WHEN NOT MATCHED THEN INSERT (id) VALUES (u.id)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'SELECT SUM(n) FILTER (WHERE n > 1) OVER (PARTITION BY k ORDER BY n ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM t'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'CREATE TABLE t (id INT GENERATED ALWAYS AS IDENTITY, value TEXT DEFAULT \'x\', CHECK(id > 0))'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'GRANT SELECT ON TABLE t TO r; REVOKE SELECT ON TABLE t FROM r'])]
    public function testAnalyzeRoundTripsCompleteStatements(Dialect $dialect, string $sql): void
    {
        $statement = (new Semantics($dialect))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter($dialect->platform()->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }


    public function testLiteralsSuppliesTheDecoder(): void
    {
        $language = new Language(Dialect::PostgreSql);
        $tokens = array_values(array_filter($language->parser()->tokenize("'x'"), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        self::assertSame('x', $language->dialect->platform()->literals($language)->decode($tokens)->value());
    }

}
