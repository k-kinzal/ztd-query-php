<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class NameRulesTest extends TestCase
{
    public function testDecodeIsTheNameOfATokenWithThatSpelling(): void
    {
        $rules = PostgreSqlDialect::PostgreSql->platform()->names();
        self::assertSame($rules->name(new Token(1, 'IDENT', '"Mixed"', 0)), $rules->decode('"Mixed"'));
        self::assertSame('mixed', $rules->decode('Mixed'));
    }

    public function testNameHonorsAnInjectedPolicy(): void
    {
        $names = self::createStub(\SqlSemantics\Core\Policy\NameRules::class);
        $names->method('name')->willReturn('application-name');
        $platform = self::createStub(\SqlSemantics\Core\Platform::class);
        $platform->method('names')->willReturn($names);
        $dialect = self::createStub(Dialect::class);
        $dialect->method('platform')->willReturn($platform);
        self::assertSame('application-name', (new \SqlSemantics\Core\Ast\Identifiers($dialect))->name(new Token(1, 'name', 'input', 0)));
    }
    public function testEqualUsesColumnCaseRules(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\NameRules $rules): \SqlSemantics\Core\Policy\NameRules => $rules;
        $rules = $accept(PostgreSqlDialect::PostgreSql->platform()->names());
        self::assertSame(false, $rules->equal('Item', 'item'));
    }
    public function testRelationEqualUsesTableCaseRules(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\NameRules $rules): \SqlSemantics\Core\Policy\NameRules => $rules;
        $rules = $accept(PostgreSqlDialect::PostgreSql->platform()->names());
        self::assertSame(false, $rules->relationEqual('Item', 'item'));
    }
    public function testKeyUsesTheSameColumnIdentity(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\NameRules $rules): \SqlSemantics\Core\Policy\NameRules => $rules;
        $rules = $accept(PostgreSqlDialect::PostgreSql->platform()->names());
        self::assertSame(false, $rules->key('Item') === $rules->key('item'));
    }
}
