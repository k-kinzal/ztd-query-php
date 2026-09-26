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
use SqlSemantics\Platform\MySql\Dialect;

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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
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
        $parser = Dialect::MySql->platform()->parser();
        self::assertNotSame('', $parser->version());
        self::assertSame('SELECT 1', $parser->parse('SELECT 1')->toString());
    }

    public function testDefaultSchemaUsesTheLanguageNamespace(): void
    {
        self::assertSame('', Dialect::MySql->platform()->defaultSchema());
    }

    public function testStatementNamesIdentifyTheParserRoot(): void
    {
        self::assertSame(Dialect::MySql->platform()->statementNames()[0], Dialect::MySql->platform()->parser()->parse('SELECT 1')->name);
    }

    public function testSyntaxRecognizesSelectBody(): void
    {
        $tree = Dialect::MySql->platform()->parser()->parse('SELECT 1');
        self::assertNotEmpty(\SqlSemantics\Core\Ast\Tree::outer($tree, Dialect::MySql->platform()->syntax()->nodes('selectBody')));
    }

    public function testNamesDecodeQuotedIdentifiers(): void
    {
        self::assertSame('Mixed', Dialect::MySql->platform()->names()->name(new Token(1, 'ID', '"Mixed"', 0)));
    }

    public function testTypesRetainDialectIdentity(): void
    {
        self::assertSame(Dialect::MySql, Dialect::MySql->platform()->types()->boolean()->dialect);
    }

    public function testSchemaKeepsDeclarations(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        self::assertCount(2, $schema->tables[0]->columns);
    }

    public function testQueryKeepsProjectionOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)');
        $bound = (new Binder($schema))->bind('SELECT label, id FROM items');
        self::assertSame(['label', 'id'], array_column($bound->outputs, 'name'));
    }
    public function testValuesReconstructsUsingTheParserRelease(): void
    {
        $platform = Dialect::MySql->platform();
        $parser = $platform->parser();
        $value = $platform->values($parser->version())->read($parser->parse('SELECT 42'));
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $value);
        self::assertSame('SELECT 42', (new \SqlSemantics\Statement\Statement($value))->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'CREATE LOGFILE GROUP logs ADD UNDOFILE \'undo.dat\''])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'SET DEFAULT .some_variable = DEFAULT'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'SHOW COUNT( * ) ERRORS'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'CREATE TABLE count (id INT)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'SELECT count (1), COUNT(*) FROM count'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'CREATE PROCEDURE p() BEGIN DECLARE n INT DEFAULT 1; WHILE n < 3 DO SET n = n + 1; END WHILE; END'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM t WHERE n<4) SELECT SUM(n) OVER (ORDER BY n) FROM t'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'INSERT INTO t (id, name) VALUES (1, \'a\') ON DUPLICATE KEY UPDATE name = VALUES(name)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'SELECT `select`.id, @@global.sql_mode FROM db.`from`'])]
    public function testAnalyzeRoundTripsCompleteStatements(Dialect $dialect, string $sql): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics($dialect))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter($dialect->platform()->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.0.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.1.0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.2.0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.3.0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.0.1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0'])]
    public function testAnalyzeWithEveryGrammarRelease(string $version): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(Dialect::MySql, $version))->analyze('DELETE FROM absent_table WHERE id = 1');
        self::assertSame('DELETE FROM absent_table WHERE id = 1', $statement->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'ALTER PROCEDURE ACTION .some_name'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44', 'ALTER DEFINER = \'text\' EVENT SQL_AFTER_GTIDS .some_name RENAME TO ACTION'])]
    public function testAnalyzePreservesKeywordNamesBeforeDots(string $version, string $sql): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(Dialect::MySql, $version))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter(Dialect::MySql->platform()->parser($version), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'statement', "\x18\x00\x00\x00\x00\x00\x15\x00\x01\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x03\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0', 'simple_statement_or_begin', "\x18\x00\x00\x00\x00\x00\x15\x00\x01\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x03\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'statement', "\x9e\x06\x00\x00\xeb\x25\x4b\xc3\xea\xf2\xf0\x9e\xea\x25\xaa\x39\x51\x9f\xcc\x54\xff\x01\xfc\xb4\x51\x47\x87\x7b\xbb\xc2\x02\xcd\x5f\x6a\xad\xb6\x44\xdd\x96\xe3\xcf\x41\x38\x8f\x2c\x08\x5e\x0f\xe3\xad\xf7\x0c\x1c\x33\x23\x51\x3a\x6d\x8e\x94\x93\xe0\x44\xee\xb4\xcf\x91\x7b\x27\x3f\x2d\xf4\x55\x47\x69\x9a\xf7\x95\x32\xfe\xc4\xb5\x4c\x6d\xb4\x26\x26\x34\xe0\xc3\x55\xa4\xef\xe2\x05\x62\x35\xc2\x72\x64\xfe\x16\x23\x2d\xe6\x0d\x5a\xbf\xa8\x33\xa9\xd0\x84\x33\xe8\x4a\x6c\x20\x39\x62\x48\x97\x0c\xf0\xb6\xae\x15\xe4\x5a\xb4\xbb\x17"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'statement', "\x95\x00\x00\x00\x99\x5f\xa8\xe1\x87\x89\xea\xd8\xbc\x44\x06\xa3\x59\x3f\xf3\xeb\xb7\xea\xab\xb0\x5d\xbd\xd4\x75\xe0\xb2\x2a\xe5\x99\xa4\x47\x4e\x6e\xd9\xa1\x1b\x14\xef\x57\xc5\x27\x38\x7c\xf3\x27\x77\x4d\x0f\xd5\xb6\xee\x61\x0e\xfe\x39\xf8\xe4\xbc\x82\x41\x5e\xa8\xff\xb5\x02\x1c\x9d\x65\xf3\x77\xf0\xe7\x24\xc5\xf1\x0e\x89\x11\xd6\x37\xe9\xf9\x97\x0a\x18\x87\xc1\x94\x27\x74\x94\xd1\x05\x33\xaf\xf3\x0d\x85\xa0\x74\x95\x1f\x97\xe2\x5b\xaa\x35\x7f\x6c\x72\xf9\x8d\x19\x13\x6b\x15\x9f\x84\x69\x8d\xe8\x42\xfd\x02\x8e\x5b\xf3\x81\x73\xc6\x60\xeb\xa8\x34\xf6\xc9\xd1\xf8\x42\x1e\xe6\xbe\xf4\x77\x21\x24\x73\xed\xf9\x16\x68\x10\xf7\xcd\xe8\x1f\x44\xae\x3c\x3d\xb1\x42\x17\x58\x24\xa6\x67\xee\xaf\x90\x79\xb3\x29\xf1\x5e\x89\x9f\xae\x3e\x19\x3a\x48\x0e\x03\xdd\x6f\xcc\xbd\xe0\x7c\xef\xe5\x19\x0e\x84\x0d\x48\x63\xf9\xd9\x02\x4a\x96\xf9\x23\x1c\xb3\xca\xd4\xb2\x2e\xc1\xbe\xf5\x42\x93\xfa\x1a\xe0\xa8\x2b\xb6\xbb\x1c\xcc\x6f\x6a\x90\x70\x46\x43\x3b\x0a\x2e\x3b\x18\x1d\x40\xa5\x79\xaf\x63\x62\xb1\x28\x85\xef\x8f\xef\x6e\x84\xdf\x8a\xac\xf2\x60"])]
    public function testAnalyzeFakerRegressions(string $version, string $root, string $input): void
    {
        $provider = new \SqlFaker\MySql\MySqlProvider(\Faker\Factory::create(), $version);
        $constraints = \SqlFaker\Generation\Plan\GenerationPlan::fromRule($root)->requiringNonEmpty();
        $plan = (new \SqlFaker\Generation\Choice\BytePlanCompiler())->compile($input, $provider->planner(), $constraints);
        $sql = $provider->generate($plan);
        $statement = (new \SqlSemantics\Facade\Semantics(Dialect::MySql, $version))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter(Dialect::MySql->platform()->parser($version), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }
}
