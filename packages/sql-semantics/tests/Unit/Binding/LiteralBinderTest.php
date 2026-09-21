<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundSelect::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
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
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Parameter, $statement->outputs[0]->expression->kind);
        self::assertSame(Nullability::Unknown, $statement->outputs[0]->expression->nullability);
        self::assertSame('unknown', $statement->outputs[0]->expression->type->name);
    }

    #[DataProvider('providerDecimalIntegers')]
    public function testIntegerResolvesDecimalBoundaries(string $literal, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Binding\LiteralBinder(Dialect::PostgreSql))->integer($literal));
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
        $reader = new \SqlSemantics\Binding\LiteralBinder(Dialect::PostgreSql);
        self::assertSame('numeric', $reader->typeName(new \SqlParser\Lexer\Token(1, 'FCONST', '1.25', 0)));
        self::assertSame('unknown', $reader->typeName(new \SqlParser\Lexer\Token(1, 'SCONST', "'value'", 0)));
        self::assertNull($reader->typeName(new \SqlParser\Lexer\Token(1, 'IDENT', 'value', 0)));
    }
    public function testNonDecimalKeepsOriginalLiteralSpelling(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 0xff');
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('0xff', $query->outputs[0]->expression->symbol);
    }

    #[TestWith(['0x7fffffff', 'integer'])]
    #[TestWith(['0x80000000', 'bigint'])]
    #[TestWith(['0x7fffffffffffffff', 'bigint'])]
    #[TestWith(['0x8000000000000000', 'numeric'])]
    #[TestWith(['0o17777777777', 'integer'])]
    #[TestWith(['0o20000000000', 'bigint'])]
    #[TestWith(['0b1111111111111111111111111111111', 'integer'])]
    #[TestWith(['0b10000000000000000000000000000000', 'bigint'])]
    public function testNonDecimalResolvesIntegerWidths(string $literal, string $type): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('select ' . $literal);
        self::assertSame($type, $query->outputs[0]->expression->type->name);
        self::assertSame($literal, $query->outputs[0]->expression->symbol);
    }

}
