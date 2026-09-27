<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\Nullability;

#[CoversClass(Nullability::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
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
#[Medium]
final class NullabilityTest extends TestCase
{
    public function testDistinguishesAnUnknownFactFromKnownNullableValues(): void
    {
        self::assertNotSame(Nullability::Unknown, Nullability::MaybeNull);
        self::assertNotSame(Nullability::AlwaysNull, Nullability::MaybeNull);
        self::assertSame('not-null', Nullability::NotNull->value);
    }
}
