<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[UsesClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Analysis\FromReader::class)]
#[UsesClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[UsesClass(\SqlSemantics\Analysis\NullFacts::class)]
#[UsesClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[UsesClass(\SqlSemantics\Analysis\SelectReader::class)]
#[UsesClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[UsesClass(\SqlSemantics\Analysis\TailReader::class)]
#[UsesClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[UsesClass(\SqlSemantics\Analyzer::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\SelectQuery::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\Catalog::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class ConstraintKindTest extends TestCase
{
    public function testDistinguishesKeysFromRowAndReferentialConditions(): void
    {
        self::assertSame(['primary-key', 'unique', 'foreign-key', 'check'], array_column(\SqlSemantics\Schema\ConstraintKind::cases(), 'value'));
    }

}
