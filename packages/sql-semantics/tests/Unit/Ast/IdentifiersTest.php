<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\SemanticException;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Analysis\TailReader::class)]
#[CoversClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Analyzer::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\SelectQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema\Catalog::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class IdentifiersTest extends TestCase
{
    public function testNamePreservesQuotedPostgresCaseAndFoldsUnquotedNames(): void
    {
        $case = new AnalysisCase();
        $schema = $case->schema('CREATE TABLE "Users" ("Id" INTEGER NOT NULL, SCORE INTEGER)');
        $query = $case->analyzer->analyze($case->parser->parse('SELECT "Id", score FROM "Users"'), $schema);
        self::assertSame(['Id', 'score'], array_column($query->outputs, 'name'));
    }
    public function testEqualDistinguishesQuotedPostgresCase(): void
    {
        $case = new AnalysisCase();
        $schema = $case->schema('CREATE TABLE "Users" ("Id" INTEGER)');
        $this->expectException(SemanticException::class);
        $case->analyzer->analyze($case->parser->parse('SELECT id FROM "Users"'), $schema);
    }


    public function testPartsDoesNotSplitDotsInsideQuotedIdentifiers(): void
    {
        $case = new AnalysisCase();
        $schema = $case->schema('CREATE TABLE "a.b" ("c.d" INTEGER)');
        $query = $case->analyzer->analyze($case->parser->parse('SELECT "a.b"."c.d" FROM "a.b"'), $schema);
        self::assertSame('a.b', $query->relations[0]->declaration->name);
        self::assertSame('c.d', $query->outputs[0]->name);
    }

    public function testRelationEqualModelsMysqlTableAliasesSeparatelyFromColumns(): void
    {
        $names = new \SqlSemantics\Ast\Identifiers(\SqlSemantics\Dialect::MySql);
        self::assertTrue($names->equal('Score', 'score'));
        self::assertFalse($names->relationEqual('Child', 'child'));
        $this->expectException(SemanticException::class);
        (new AnalysisCase(\SqlSemantics\Dialect::MySql))->query('SELECT CHILD.id FROM users child');
    }

    #[DataProvider('providerIdentifiers')]
    public function testNameUnquotesEscapedDelimiters(\SqlSemantics\Dialect $dialect, string $text, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Ast\Identifiers($dialect))->name(new \SqlParser\Lexer\Token(1, 'ID', $text, 0)));
    }

    /**
     * @return iterable<string, array{\SqlSemantics\Dialect, string, string}>
     */
    public static function providerIdentifiers(): iterable
    {
        yield 'postgres unquoted' => [\SqlSemantics\Dialect::PostgreSql, 'USERS', 'users'];
        yield 'postgres quoted' => [\SqlSemantics\Dialect::PostgreSql, '"Users"', 'Users'];
        yield 'escaped double quote' => [\SqlSemantics\Dialect::PostgreSql, '"a""b"', 'a"b'];
        yield 'mysql unquoted' => [\SqlSemantics\Dialect::MySql, 'USERS', 'USERS'];
        yield 'mysql backtick' => [\SqlSemantics\Dialect::MySql, '`a``b`', 'a`b'];
        yield 'sqlite bracket' => [\SqlSemantics\Dialect::Sqlite, '[a b]', 'a b'];
    }
}
