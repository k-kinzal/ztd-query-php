<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Affinity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\IntervalFields::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeRulesTest extends TestCase
{
    public function testReadPreservesPrecisionAndScale(): void
    {
        $schema = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE items (value DECIMAL(10, 2))', []));
        self::assertSame(\SqlSemantics\Statement\Declaration\Builtin::Numeric, $schema->declarations[0]->columns[0]->type->name);
        self::assertSame(10, $schema->declarations[0]->columns[0]->type->precision);
        self::assertSame(2, $schema->declarations[0]->columns[0]->type->scale);
    }

    public function testSupportsTheDeclaredTypeVocabulary(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\Sqlite\TypeRules())->supports(\SqlSemantics\Statement\Declaration\Builtin::Integer));
        self::assertFalse((new \SqlSemantics\Platform\Sqlite\TypeRules())->supports(\SqlSemantics\Statement\Declaration\Builtin::TsVector) && (new \SqlSemantics\Platform\Sqlite\TypeRules())->supports(\SqlSemantics\Statement\Declaration\Builtin::MediumInt) && (new \SqlSemantics\Platform\Sqlite\TypeRules())->supports(\SqlSemantics\Statement\Declaration\Builtin::Any));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['ANY'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['"ANY"'])]
    public function testReadPreservesAnyValuesInStrictTables(string $declaredType): void
    {
        $table = (new \SqlParser\Sqlite\SqliteParser())->parse('CREATE TABLE t (value ' . $declaredType . ') STRICT');
        $values = Dialect::Sqlite->platform()->values(Dialect::Sqlite->platform()->parser()->version());
        $type = (new \SqlSemantics\Platform\Sqlite\TypeRules())->read($table->find('typetoken')[0], $values, $table)->type;
        self::assertSame(\SqlSemantics\Statement\Declaration\Builtin::Any, $type->name);
        self::assertSame(\SqlSemantics\Statement\Declaration\Affinity::Blob, $type->affinity);
    }
}
