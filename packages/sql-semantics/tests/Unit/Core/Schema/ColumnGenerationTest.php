<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Writer;

#[\PHPUnit\Framework\Attributes\UsesClass(SchemaFacade::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
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
final class ColumnGenerationTest extends TestCase
{
    public function testIdentityKeepsItsOptionsWithoutAComputation(): void
    {
        $column = (new Schema(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE t (id INT GENERATED ALWAYS AS IDENTITY (START WITH 10))')->tables[0]->columns[0];
        self::assertNotNull($column->generation);
        self::assertSame('identity', $column->generation->kind->value);
        self::assertNull($column->generation->expression);
        self::assertStringContainsString('START WITH 10', Writer::render($column->generation->clause));
    }

    public function testComputedGenerationKeepsAnIndependentExpression(): void
    {
        $column = (new Schema(SqliteDialect::Sqlite))->analyze('CREATE TABLE t (id INT, x INT AS (id * 2) STORED)')->tables[0]->columns[1];
        self::assertNotNull($column->generation);
        self::assertNotNull($column->generation->expression);
        self::assertSame('id * 2', Writer::render($column->generation->expression));
    }
}
