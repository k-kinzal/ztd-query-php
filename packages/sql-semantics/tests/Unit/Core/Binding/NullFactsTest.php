<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Facade\Dialect;

#[CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Core\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Core\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Core\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Core\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Core\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Core\Model\Join::class)]
#[CoversClass(\SqlSemantics\Core\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Core\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Core\Model\BoundSelect::class)]
#[CoversClass(\SqlSemantics\Core\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
final class NullFactsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testCoalesceRequiresANonNullOperandToGuaranteeAValue(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT COALESCE(parent_id, NULL) AS a, COALESCE(NULL, NULL) AS b, COALESCE(parent_id, score) AS c FROM users');
        self::assertSame(Nullability::MaybeNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(Nullability::AlwaysNull, $statement->outputs[1]->expression->nullability);
        self::assertSame(Nullability::NotNull, $statement->outputs[2]->expression->nullability);
    }

    public function testStrictComparisonPropagatesNullButBooleanOrDoesNot(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT NULL = 1 AS a, NULL OR TRUE AS b');
        self::assertSame(Nullability::AlwaysNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $statement->outputs[1]->expression->nullability);
    }

    public function testExtensionsRetainsAllNullableOperandCauses(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id+b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(Nullability::MaybeNull, $statement->outputs[0]->expression->nullability);
    }

    #[DataProvider('providerFacts')]
    public function testStrictAndCoalesceFacts(Nullability $left, Nullability $right, Nullability $strict, Nullability $coalesce): void
    {
        $type = new \SqlSemantics\Core\Type\TypeDescriptor(Dialect::PostgreSql, 'integer');
        $source = new \SqlParser\Parser\Node('expr', 0, []);
        $operands = [
            new \SqlSemantics\Core\Model\Expression(\SqlSemantics\Core\Model\ExpressionKind::Literal, $type, $left, $source),
            new \SqlSemantics\Core\Model\Expression(\SqlSemantics\Core\Model\ExpressionKind::Literal, $type, $right, $source),
        ];
        self::assertSame($strict, \SqlSemantics\Core\Binding\NullFacts::strict($operands));
        self::assertSame($coalesce, \SqlSemantics\Core\Binding\NullFacts::coalesce($operands));
    }

    /**
     * @return iterable<string, array{Nullability, Nullability, Nullability, Nullability}>
     */
    public static function providerFacts(): iterable
    {
        yield 'NotNull-NotNull' => [Nullability::NotNull, Nullability::NotNull, Nullability::NotNull, Nullability::NotNull];
        yield 'NotNull-MaybeNull' => [Nullability::NotNull, Nullability::MaybeNull, Nullability::MaybeNull, Nullability::NotNull];
        yield 'NotNull-AlwaysNull' => [Nullability::NotNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::NotNull];
        yield 'NotNull-Unknown' => [Nullability::NotNull, Nullability::Unknown, Nullability::Unknown, Nullability::NotNull];
        yield 'MaybeNull-NotNull' => [Nullability::MaybeNull, Nullability::NotNull, Nullability::MaybeNull, Nullability::NotNull];
        yield 'MaybeNull-MaybeNull' => [Nullability::MaybeNull, Nullability::MaybeNull, Nullability::MaybeNull, Nullability::MaybeNull];
        yield 'MaybeNull-AlwaysNull' => [Nullability::MaybeNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::MaybeNull];
        yield 'MaybeNull-Unknown' => [Nullability::MaybeNull, Nullability::Unknown, Nullability::Unknown, Nullability::Unknown];
        yield 'AlwaysNull-NotNull' => [Nullability::AlwaysNull, Nullability::NotNull, Nullability::AlwaysNull, Nullability::NotNull];
        yield 'AlwaysNull-MaybeNull' => [Nullability::AlwaysNull, Nullability::MaybeNull, Nullability::AlwaysNull, Nullability::MaybeNull];
        yield 'AlwaysNull-AlwaysNull' => [Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::AlwaysNull];
        yield 'AlwaysNull-Unknown' => [Nullability::AlwaysNull, Nullability::Unknown, Nullability::AlwaysNull, Nullability::Unknown];
        yield 'Unknown-NotNull' => [Nullability::Unknown, Nullability::NotNull, Nullability::Unknown, Nullability::NotNull];
        yield 'Unknown-MaybeNull' => [Nullability::Unknown, Nullability::MaybeNull, Nullability::Unknown, Nullability::Unknown];
        yield 'Unknown-AlwaysNull' => [Nullability::Unknown, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::Unknown];
        yield 'Unknown-Unknown' => [Nullability::Unknown, Nullability::Unknown, Nullability::Unknown, Nullability::Unknown];
    }
}
