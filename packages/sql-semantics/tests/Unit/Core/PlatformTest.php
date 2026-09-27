<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Language::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\RelationRules::class)]
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
final class PlatformTest extends TestCase
{
    public function testParserAcceptsAnApplicationImplementation(): void
    {
        $parser = self::createStub(\SqlParser\Parser\SqlParser::class);
        $parser->method('version')->willReturn('application-1');
        $platform = self::createStub(\SqlSemantics\Core\Platform::class);
        $platform->method('parser')->willReturn($parser);
        $dialect = self::createStub(Dialect::class);
        $dialect->method('platform')->willReturn($platform);
        self::assertSame('application-1', (new \SqlSemantics\Core\Ast\DialectParser(new \SqlSemantics\Core\Language($dialect)))->version());
    }
    public function testDefaultSchemaUsesTheLanguageNamespace(): void
    {
        $accept = static fn (\SqlSemantics\Core\Platform $rules): \SqlSemantics\Core\Platform => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()::class, $accept(PostgreSqlDialect::PostgreSql->platform())::class);
        self::assertSame('public', PostgreSqlDialect::PostgreSql->platform()->defaultSchema());
    }
    public function testStatementNamesIdentifyTheParserRoot(): void
    {
        $accept = static fn (\SqlSemantics\Core\Platform $rules): \SqlSemantics\Core\Platform => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()::class, $accept(PostgreSqlDialect::PostgreSql->platform())::class);
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->statementNames()[0], PostgreSqlDialect::PostgreSql->platform()->parser()->parse('SELECT 1')->name);
    }
    public function testSyntaxRecognizesTheCreateTableDeclaration(): void
    {
        $accept = static fn (\SqlSemantics\Core\Platform $rules): \SqlSemantics\Core\Platform => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()::class, $accept(PostgreSqlDialect::PostgreSql->platform())::class);
        $tree = PostgreSqlDialect::PostgreSql->platform()->parser()->parse('CREATE TABLE t (id INTEGER)');
        self::assertNotEmpty(\SqlSemantics\Core\Ast\Tree::outer($tree, PostgreSqlDialect::PostgreSql->platform()->syntax()->nodes('createTable')));
    }
    public function testNamesDecodeQuotedIdentifiers(): void
    {
        $accept = static fn (\SqlSemantics\Core\Platform $rules): \SqlSemantics\Core\Platform => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()::class, $accept(PostgreSqlDialect::PostgreSql->platform())::class);
        self::assertSame('Mixed', PostgreSqlDialect::PostgreSql->platform()->names()->name(new Token(1, 'ID', '"Mixed"', 0)));
    }
    public function testTypesReadDeclaredTypes(): void
    {
        $accept = static fn (\SqlSemantics\Core\Platform $rules): \SqlSemantics\Core\Platform => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()::class, $accept(PostgreSqlDialect::PostgreSql->platform())::class);
        self::assertTrue(PostgreSqlDialect::PostgreSql->platform()->types()->supports(\SqlSemantics\Statement\Declaration\Builtin::Integer));
    }
    public function testSchemaKeepsDeclarations(): void
    {
        $accept = static fn (\SqlSemantics\Core\Platform $rules): \SqlSemantics\Core\Platform => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()::class, $accept(PostgreSqlDialect::PostgreSql->platform())::class);
        $schema = Resolved::of((new Semantics(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)', []));
        self::assertCount(2, $schema->declarations[0]->columns);
    }
    public function testRelationsNameTheTableSymbolsOfTheGrammar(): void
    {
        self::assertContains('qualified_name', PostgreSqlDialect::PostgreSql->platform()->relations()->nameSymbols);
        self::assertNotEmpty(PostgreSqlDialect::PostgreSql->platform()->relations()->declarations);
    }
    public function testBuilderComposesValuesOfTheLanguage(): void
    {
        $language = new \SqlSemantics\Core\Language(PostgreSqlDialect::PostgreSql);
        $builder = PostgreSqlDialect::PostgreSql->platform()->builder($language);
        self::assertSame('"Mixed" = 1', \SqlSemantics\Statement\Writer::render($builder->compare($builder->column('Mixed'), '=', $builder->integer(1))));
    }
    public function testValuesReconstructsUsingTheParserRelease(): void
    {
        $platform = PostgreSqlDialect::PostgreSql->platform();
        $parser = $platform->parser();
        $value = $platform->values($parser->version())->read($parser->parse('SELECT 42'));
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $value);
        self::assertSame('SELECT 42', (new \SqlSemantics\Statement\Statement($value))->toString());
    }
}
