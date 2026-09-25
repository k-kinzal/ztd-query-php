<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Facade\Dialect;

#[CoversClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Core\Binding\NullFacts::class)]
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
final class LiteralBinderTest extends TestCase
{
    public function testIntegerClassifiesPostgresWidthsWithoutPhpOverflow(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT 2147483647, 2147483648, 9223372036854775808');
        self::assertSame('integer', $statement->outputs[0]->expression->type->name);
        self::assertSame('bigint', $statement->outputs[1]->expression->type->name);
        self::assertSame('numeric', $statement->outputs[2]->expression->type->name);
    }

    public function testBindParametersRemainExplicitlyUnknownWithoutBindings(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT $1');
        self::assertSame(\SqlSemantics\Core\Model\ExpressionKind::Parameter, $statement->outputs[0]->expression->kind);
        self::assertSame(Nullability::Unknown, $statement->outputs[0]->expression->nullability);
        self::assertSame('unknown', $statement->outputs[0]->expression->type->name);
    }

    #[DataProvider('providerDecimalIntegers')]
    public function testIntegerResolvesDecimalBoundaries(string $literal, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Core\Binding\LiteralBinder(Dialect::PostgreSql))->integer($literal));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDecimalIntegers(): iterable
    {
        yield '0' => ['0', 'integer'];
        yield '00001' => ['00001', 'integer'];
        yield '2147483646' => ['2147483646', 'integer'];
        yield '2147483647' => ['2147483647', 'integer'];
        yield '2147483648' => ['2147483648', 'bigint'];
        yield '999999999' => ['999999999', 'integer'];
        yield '9999999999' => ['9999999999', 'bigint'];
        yield '10000000000' => ['10000000000', 'bigint'];
        yield '0002147483648' => ['0002147483648', 'bigint'];
        yield '9223372036854775806' => ['9223372036854775806', 'bigint'];
        yield '9223372036854775807' => ['9223372036854775807', 'bigint'];
        yield '9223372036854775808' => ['9223372036854775808', 'numeric'];
        yield '10000000000000000000' => ['10000000000000000000', 'numeric'];
    }

    public function testBindDistinguishesMysqlUnsignedAndDecimalBoundaries(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $statement = (new Binder($schema))->bind('SELECT 2147483648, 9223372036854775808, 18446744073709551616');
        self::assertSame('bigint', $statement->outputs[0]->expression->type->name);
        self::assertSame('bigint unsigned', $statement->outputs[1]->expression->type->name);
        self::assertSame('numeric', $statement->outputs[2]->expression->type->name);
    }

    public function testBindDistinguishesSqliteOverflowAsReal(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build();
        $statement = (new Binder($schema))->bind('SELECT 9223372036854775807, 9223372036854775808');
        self::assertSame('integer', $statement->outputs[0]->expression->type->name);
        self::assertSame('real', $statement->outputs[1]->expression->type->name);
    }

    public function testTypeNameRetainsLiteralCategoriesAndRejectsIdentifiers(): void
    {
        $reader = new \SqlSemantics\Core\Binding\LiteralBinder(Dialect::PostgreSql);
        self::assertSame('numeric', $reader->typeName(new \SqlParser\Lexer\Token(1, 'FCONST', '1.25', 0)));
        self::assertSame('unknown', $reader->typeName(new \SqlParser\Lexer\Token(1, 'SCONST', "'value'", 0)));
        self::assertNull($reader->typeName(new \SqlParser\Lexer\Token(1, 'IDENT', 'value', 0)));
    }
}
