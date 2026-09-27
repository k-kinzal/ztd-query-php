<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaFacade::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
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
final class TypeRulesTest extends TestCase
{
    public function testReadPreservesPrecisionAndScale(): void
    {
        $schema = (new SchemaFacade(Dialect::Sqlite))->analyze('CREATE TABLE items (value DECIMAL(10, 2))');
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Numeric, $schema->tables[0]->columns[0]->type->name);
        self::assertSame(10, $schema->tables[0]->columns[0]->type->precision);
        self::assertSame(2, $schema->tables[0]->columns[0]->type->scale);
    }

    public function testSupportsTheDeclaredTypeVocabulary(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\Sqlite\TypeRules(Dialect::Sqlite))->supports(\SqlSemantics\Core\Type\Builtin::Integer));
        self::assertFalse((new \SqlSemantics\Platform\Sqlite\TypeRules(Dialect::Sqlite))->supports(\SqlSemantics\Core\Type\Builtin::TsVector) && (new \SqlSemantics\Platform\Sqlite\TypeRules(Dialect::Sqlite))->supports(\SqlSemantics\Core\Type\Builtin::MediumInt) && (new \SqlSemantics\Platform\Sqlite\TypeRules(Dialect::Sqlite))->supports(\SqlSemantics\Core\Type\Builtin::Any));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['ANY'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['"ANY"'])]
    public function testReadPreservesAnyValuesInStrictTables(string $declaredType): void
    {
        $table = (new \SqlParser\Sqlite\SqliteParser())->parse('CREATE TABLE t (value ' . $declaredType . ') STRICT');
        $values = Dialect::Sqlite->platform()->values(Dialect::Sqlite->platform()->parser()->version());
        $type = (new \SqlSemantics\Platform\Sqlite\TypeRules(Dialect::Sqlite))->read($table->find('typetoken')[0], $values, $table)->type;
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Any, $type->name);
        self::assertSame(\SqlSemantics\Core\Type\Affinity::Blob, $type->affinity);
    }
}
