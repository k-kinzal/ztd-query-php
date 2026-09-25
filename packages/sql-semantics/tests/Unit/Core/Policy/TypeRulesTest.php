<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Expression::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeRulesTest extends TestCase
{
    public function testCanonicalHonorsAnInjectedPolicy(): void
    {
        $types = self::createStub(\SqlSemantics\Core\Policy\TypeRules::class);
        $types->method('canonical')->willReturn('application-type');
        $platform = self::createStub(\SqlSemantics\Core\Platform::class);
        $platform->method('types')->willReturn($types);
        $dialect = self::createStub(\SqlSemantics\Core\Dialect::class);
        $dialect->method('platform')->willReturn($platform);
        self::assertSame('application-type', (new \SqlSemantics\Core\Ast\TypeReader($dialect))->canonical('INPUT'));
    }
    public function testReadPreservesModifiers(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        self::assertSame(Dialect::PostgreSql->platform()->types()::class, $accept(Dialect::PostgreSql->platform()->types())::class);
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE items (value DECIMAL(10, 2))');
        self::assertSame(['10', '2'], $schema->tables[0]->columns[0]->type->modifiers);
    }
    public function testAffinityUsesDeclaredTypePrecedence(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        self::assertSame('integer', $rules->affinity('FLOATING POINT'));
    }
    public function testTypeNameClassifiesIntegerToken(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        self::assertSame('integer', $rules->typeName(new Token(1, 'ICONST', '42', 0)));
    }
    public function testIntegerModelsLargeMagnitude(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        self::assertSame('bigint', $rules->integer('2147483648'));
    }
    public function testCommonPreservesHomogeneousType(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, 'integer'), Nullability::NotNull, $source, symbol: '1');
        self::assertSame('integer', $rules->common([$expression], $source)->name);
    }
    public function testBooleanNamesPredicateResult(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        self::assertSame('boolean', $rules->boolean()->name);
    }
    public function testArithmeticRetainsLanguageSemantics(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, 'integer'), Nullability::NotNull, $source, symbol: '1');
        self::assertSame('integer', $rules->arithmetic('+', [$expression], $source)->name);
    }
    public function testPredicateAcceptsBooleanResults(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, $rules->boolean(), Nullability::NotNull, $source);
        $rules->predicate($expression);
        self::assertSame($source, $expression->source);
    }
    public function testCoalescePreservesOperands(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, 'integer'), Nullability::NotNull, $source, symbol: '1');
        self::assertSame([$expression], $rules->coalesce([$expression], $expression->type));
    }
    public function testProjectPreservesTypedValues(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(Dialect::PostgreSql->platform()->types());
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, 'integer'), Nullability::NotNull, $source, symbol: '1');
        self::assertSame($expression, $rules->project($expression));
    }
}
