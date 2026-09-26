<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;

#[CoversClass(\SqlSemantics\Core\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Core\Schema::class)]
#[UsesClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
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
#[Medium]
final class ConstraintKindTest extends TestCase
{
    public function testDistinguishesKeysFromRowAndReferentialConditions(): void
    {
        self::assertSame(['primary-key', 'unique', 'foreign-key', 'check'], array_column(\SqlSemantics\Core\Schema\ConstraintKind::cases(), 'value'));
    }
}
