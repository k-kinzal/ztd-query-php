<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;

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
#[\PHPUnit\Framework\Attributes\Medium]
final class NameRulesTest extends TestCase
{
    public function testDecodeReadsQuotedAndBareSpellings(): void
    {
        $rules = Dialect::Sqlite->platform()->names();
        self::assertSame('a"b', $rules->decode('"a""b"'));
        self::assertSame('Mixed', $rules->decode('Mixed'));
    }

    public function testNameDecodesEscapedQuotes(): void
    {
        self::assertSame('a"b', (new \SqlSemantics\Platform\Sqlite\NameRules())->name(new Token(1, 'ID', '"a""b"', 0)));
    }

    public function testEqualUsesColumnCaseRules(): void
    {
        self::assertSame(true, (new \SqlSemantics\Platform\Sqlite\NameRules())->equal('Item', 'item'));
    }

    public function testRelationEqualUsesTableCaseRules(): void
    {
        self::assertSame(true, (new \SqlSemantics\Platform\Sqlite\NameRules())->relationEqual('Item', 'item'));
    }

    public function testKeyUsesTheSameColumnIdentity(): void
    {
        self::assertSame(true, (new \SqlSemantics\Platform\Sqlite\NameRules())->key('Item') === (new \SqlSemantics\Platform\Sqlite\NameRules())->key('item'));
    }
}
