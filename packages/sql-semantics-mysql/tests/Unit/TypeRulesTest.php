<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\MySql\Dialect;

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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeRulesTest extends TestCase
{
    public function testReadPreservesModifiers(): void
    {
        $schema = (new SchemaFacade(Dialect::MySql))->analyze('CREATE TABLE items (value DECIMAL(10, 2))');
        self::assertSame(['10', '2'], $schema->tables[0]->columns[0]->type->modifiers);
    }

    public function testCanonicalResolvesInteger(): void
    {
        self::assertSame('integer', (new \SqlSemantics\Platform\MySql\TypeRules(Dialect::MySql))->canonical('INT'));
    }

    public function testAffinityUsesDeclaredTypePrecedence(): void
    {
        self::assertSame('integer', (new \SqlSemantics\Platform\MySql\TypeRules(Dialect::MySql))->affinity('FLOATING POINT'));
    }








}
