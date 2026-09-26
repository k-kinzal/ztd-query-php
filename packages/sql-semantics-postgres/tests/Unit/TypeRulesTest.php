<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Platform\PostgreSql\Dialect;

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
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeRulesTest extends TestCase
{
    public function testReadPreservesModifiers(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE items (value DECIMAL(10, 2))');
        self::assertSame(10, $schema->tables[0]->columns[0]->type->precision);
        self::assertSame(2, $schema->tables[0]->columns[0]->type->scale);
    }

    public function testSupportsTheDeclaredTypeVocabulary(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->supports(\SqlSemantics\Core\Type\Builtin::Integer));
        self::assertFalse((new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->supports(\SqlSemantics\Core\Type\Builtin::TsVector) && (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->supports(\SqlSemantics\Core\Type\Builtin::MediumInt) && (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->supports(\SqlSemantics\Core\Type\Builtin::Any));
    }

    public function testLiteralClassifiesIntegerToken(): void
    {
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Integer, (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->literal(new Token(1, 'ICONST', '42', 0))?->name);
        self::assertNull((new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->literal(new Token(1, 'IDENT', 'value', 0)));
    }

    public function testIntegerModelsLargeMagnitude(): void
    {
        self::assertSame(\SqlSemantics\Core\Type\Builtin::BigInt, (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->integer('2147483648'));
    }

    public function testCommonPreservesHomogeneousType(): void
    {
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Core\Type\Builtin::Integer), Nullability::NotNull, $source, symbol: '1');
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Integer, (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->common([$expression], $source)->name);
    }

    public function testBooleanNamesPredicateResult(): void
    {
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Boolean, (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->boolean()->name);
    }

    public function testArithmeticRetainsLanguageSemantics(): void
    {
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Core\Type\Builtin::Integer), Nullability::NotNull, $source, symbol: '1');
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Integer, (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->arithmetic(\SqlSemantics\Core\Model\Operator::Plus, [$expression], $source)->name);
    }

    public function testPredicateAcceptsBooleanResults(): void
    {
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->boolean(), Nullability::NotNull, $source, symbol: 'TRUE');
        (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->predicate($expression);
        self::assertSame($source, $expression->source);
    }

    public function testCoalescePreservesOperands(): void
    {
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Core\Type\Builtin::Integer), Nullability::NotNull, $source, symbol: '1');
        self::assertSame([$expression], (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->coalesce([$expression], $expression->type));
    }

    public function testProjectPreservesTypedValues(): void
    {
        $source = new Node('value', 0, []);
        $expression = new Expression(ExpressionKind::Literal, new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Core\Type\Builtin::Integer), Nullability::NotNull, $source, symbol: '1');
        self::assertSame($expression, (new \SqlSemantics\Platform\PostgreSql\TypeRules(Dialect::PostgreSql))->project($expression));
    }
}
