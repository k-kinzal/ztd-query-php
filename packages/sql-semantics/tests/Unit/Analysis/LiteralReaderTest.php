<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Analysis\TailReader::class)]
#[CoversClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Analyzer::class)]
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
#[CoversClass(\SqlSemantics\Model\SelectQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema\Catalog::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class LiteralReaderTest extends TestCase
{
    public function testIntegerClassifiesPostgresWidthsWithoutPhpOverflow(): void
    {
        $query = (new AnalysisCase())->query('SELECT 2147483647, 2147483648, 9223372036854775808');
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('bigint', $query->outputs[1]->expression->type->name);
        self::assertSame('numeric', $query->outputs[2]->expression->type->name);
    }
    public function testReadParametersRemainExplicitlyUnknownWithoutBindings(): void
    {
        $query = (new AnalysisCase())->query('SELECT $1');
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Parameter, $query->outputs[0]->expression->kind);
        self::assertSame(Nullability::Unknown, $query->outputs[0]->expression->nullability);
        self::assertSame('unknown', $query->outputs[0]->expression->type->name);
    }


    #[DataProvider('providerDecimalIntegers')]
    public function testIntegerResolvesDecimalBoundaries(string $literal, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Analysis\LiteralReader(\SqlSemantics\Dialect::PostgreSql))->integer($literal));
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

    public function testReadDistinguishesMysqlUnsignedAndDecimalBoundaries(): void
    {
        $query = (new AnalysisCase(\SqlSemantics\Dialect::MySql))->query('SELECT 2147483648, 9223372036854775808, 18446744073709551616');
        self::assertSame('bigint', $query->outputs[0]->expression->type->name);
        self::assertSame('bigint unsigned', $query->outputs[1]->expression->type->name);
        self::assertSame('numeric', $query->outputs[2]->expression->type->name);
    }

    public function testReadDistinguishesSqliteOverflowAsReal(): void
    {
        $query = (new AnalysisCase(\SqlSemantics\Dialect::Sqlite))->query('SELECT 9223372036854775807, 9223372036854775808');
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('real', $query->outputs[1]->expression->type->name);
    }

    public function testTypeNameRetainsLiteralCategoriesAndRejectsIdentifiers(): void
    {
        $reader = new \SqlSemantics\Analysis\LiteralReader(\SqlSemantics\Dialect::PostgreSql);
        self::assertSame('numeric', $reader->typeName(new \SqlParser\Lexer\Token(1, 'FCONST', '1.25', 0)));
        self::assertSame('unknown', $reader->typeName(new \SqlParser\Lexer\Token(1, 'SCONST', "'value'", 0)));
        self::assertNull($reader->typeName(new \SqlParser\Lexer\Token(1, 'IDENT', 'value', 0)));
    }
}
