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
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

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
#[CoversClass(\SqlSemantics\Core\Type\Builtin::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeName::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Model\Operator::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Core\Schema\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
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
        $schema = (new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT 2147483647, 2147483648, 9223372036854775808');
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Integer, $statement->outputs[0]->expression->type->name);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::BigInt, $statement->outputs[1]->expression->type->name);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Numeric, $statement->outputs[2]->expression->type->name);
    }

    public function testBindParametersRemainExplicitlyUnknownWithoutBindings(): void
    {
        $schema = (new SchemaBuilder(PostgreSqlDialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT $1');
        self::assertSame(\SqlSemantics\Core\Model\ExpressionKind::Parameter, $statement->outputs[0]->expression->kind);
        self::assertSame(Nullability::Unknown, $statement->outputs[0]->expression->nullability);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Unknown, $statement->outputs[0]->expression->type->name);
    }

    #[DataProvider('providerDecimalIntegers')]
    public function testIntegerResolvesDecimalBoundaries(string $literal, \SqlSemantics\Core\Type\Builtin $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Core\Binding\LiteralBinder(PostgreSqlDialect::PostgreSql))->integer($literal));
    }

    /**
     * @return iterable<string, array{string, \SqlSemantics\Core\Type\Builtin}>
     */
    public static function providerDecimalIntegers(): iterable
    {
        yield '0' => ['0', \SqlSemantics\Core\Type\Builtin::Integer];
        yield '00001' => ['00001', \SqlSemantics\Core\Type\Builtin::Integer];
        yield '2147483646' => ['2147483646', \SqlSemantics\Core\Type\Builtin::Integer];
        yield '2147483647' => ['2147483647', \SqlSemantics\Core\Type\Builtin::Integer];
        yield '2147483648' => ['2147483648', \SqlSemantics\Core\Type\Builtin::BigInt];
        yield '999999999' => ['999999999', \SqlSemantics\Core\Type\Builtin::Integer];
        yield '9999999999' => ['9999999999', \SqlSemantics\Core\Type\Builtin::BigInt];
        yield '10000000000' => ['10000000000', \SqlSemantics\Core\Type\Builtin::BigInt];
        yield '0002147483648' => ['0002147483648', \SqlSemantics\Core\Type\Builtin::BigInt];
        yield '9223372036854775806' => ['9223372036854775806', \SqlSemantics\Core\Type\Builtin::BigInt];
        yield '9223372036854775807' => ['9223372036854775807', \SqlSemantics\Core\Type\Builtin::BigInt];
        yield '9223372036854775808' => ['9223372036854775808', \SqlSemantics\Core\Type\Builtin::Numeric];
        yield '10000000000000000000' => ['10000000000000000000', \SqlSemantics\Core\Type\Builtin::Numeric];
    }

    public function testBindDistinguishesMysqlUnsignedAndDecimalBoundaries(): void
    {
        $schema = (new SchemaBuilder(MySqlDialect::MySql))->build();
        $statement = (new Binder($schema))->bind('SELECT 2147483648, 9223372036854775808, 18446744073709551616');
        self::assertSame(\SqlSemantics\Core\Type\Builtin::BigInt, $statement->outputs[0]->expression->type->name);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::BigInt, $statement->outputs[1]->expression->type->name);
        self::assertTrue($statement->outputs[1]->expression->type->unsigned);
        self::assertFalse($statement->outputs[0]->expression->type->unsigned);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Numeric, $statement->outputs[2]->expression->type->name);
    }

    public function testBindDistinguishesSqliteOverflowAsReal(): void
    {
        $schema = (new SchemaBuilder(SqliteDialect::Sqlite))->build();
        $statement = (new Binder($schema))->bind('SELECT 9223372036854775807, 9223372036854775808');
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Integer, $statement->outputs[0]->expression->type->name);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Real, $statement->outputs[1]->expression->type->name);
    }

    public function testLiteralRetainsLiteralCategoriesAndRejectsIdentifiers(): void
    {
        $reader = new \SqlSemantics\Core\Binding\LiteralBinder(PostgreSqlDialect::PostgreSql);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Numeric, $reader->literal(new \SqlParser\Lexer\Token(1, 'FCONST', '1.25', 0))?->name);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Unknown, $reader->literal(new \SqlParser\Lexer\Token(1, 'SCONST', "'value'", 0))?->name);
        self::assertNull($reader->literal(new \SqlParser\Lexer\Token(1, 'IDENT', 'value', 0)));
    }
}
