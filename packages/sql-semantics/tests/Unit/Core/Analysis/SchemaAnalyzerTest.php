<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Statement\Writer;

#[UsesClass(SchemaFacade::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[Medium]
#[CoversClass(\SqlSemantics\Core\Analysis\SchemaAnalyzer::class)]
#[CoversClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnProperties::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaChanges::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnGeneration::class)]
#[CoversClass(\SqlSemantics\Core\Schema\Invariant::class)]
#[CoversClass(Schema::class)]
#[CoversClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[CoversClass(Writer::class)]
#[CoversClass(\SqlSemantics\Statement\Assertion::class)]
final class SchemaAnalyzerTest extends TestCase
{
    public function testAnalyzeUsesTheSelectedGrammarForState(): void
    {
        $reader = new \SqlSemantics\Core\Analysis\SchemaAnalyzer(new \SqlSemantics\Core\Language(MySqlDialect::MySql, 'mysql-8.0.44'));
        $state = $reader->analyze('CREATE TABLE t (id INT) ENGINE=InnoDB');
        self::assertSame('mysql-8.0.44', $state->grammarVersion);
        self::assertSame('integer', $state->tables[0]->columns[0]->type->name);
    }

    public function testReadPreservesCompatibilitySyntaxErrors(): void
    {
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        (new \SqlSemantics\Core\Analysis\SchemaAnalyzer(new \SqlSemantics\Core\Language(PostgreSqlDialect::PostgreSql)))->read('CREATE TABLE');
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44'])]
    public function testAnalyzeLegacyStateUsesVersionedModels(string $version): void
    {
        $state = (new Schema(MySqlDialect::MySql, grammarVersion: $version))->analyze('CREATE TABLE t (id INT PRIMARY KEY, label VARCHAR(10) COLLATE utf8_bin) ENGINE=InnoDB');
        self::assertSame($version, $state->grammarVersion);
        self::assertSame(['id', 'label'], array_column($state->tables[0]->columns, 'name'));
        self::assertNotNull($state->tables[0]->columns[1]->collation);
        self::assertSame('COLLATE utf8_bin', Writer::render($state->tables[0]->columns[1]->collation));
    }

}
