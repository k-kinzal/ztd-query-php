<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\Sequence\SequenceInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(SequenceInvariant::class)]
#[Medium]
final class SequenceInvariantTest extends TestCase
{
    public function testDialectRejectsAnotherDatabaseLanguage(): void
    {
        SequenceInvariant::dialect((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::dialect((new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin);
    }

    public function testOptionsKeysEachParameter(): void
    {
        $options = SequenceInvariant::options([Identity\SequenceFlag::NoMinValue, new Identity\SetSequenceOwner(null), new Identity\RestartIdentity(null)]);
        self::assertSame(['MinValue', 'owner', 'restart'], array_keys($options));
    }

    public function testOptionsRejectsAConflictingFlag(): void
    {
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::options([Identity\SequenceFlag::Cycle, Identity\SequenceFlag::NoCycle]);
    }

    public function testOptionsRejectsAType(): void
    {
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::options([new Identity\SequenceStorage(TypeDescriptor::builtin(Dialect::PostgreSql, 'numeric'))]);
    }

    public function testOptionsRejectsAnOwnerWithoutTable(): void
    {
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::options([new Identity\SetSequenceOwner(new QualifiedName(['a']))]);
    }

    public function testOptionsRejectsAZeroIncrement(): void
    {
        $zero = Expression::literal(0, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $zero);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::options([new Identity\SequenceValueChange(Identity\SequenceAttribute::Increment, $zero)]);
    }

    public function testKeyRejectsPersistenceFlags(): void
    {
        self::assertSame('cycle', SequenceInvariant::key(Identity\SequenceFlag::NoCycle));
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::key(Identity\SequenceFlag::Logged);
    }

    public function testValueReadsAnExplicitNumber(): void
    {
        $cache = Expression::literal(20, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $cache);
        $options = SequenceInvariant::options([new Identity\SequenceValueChange(Identity\SequenceAttribute::Cache, $cache)]);
        self::assertSame([20, null], [SequenceInvariant::value($options, Identity\SequenceAttribute::Cache), SequenceInvariant::value($options, Identity\SequenceAttribute::Start)]);
    }

    #[TestWith(['-9223372036854775808', PHP_INT_MIN])]
    #[TestWith(['+0042', 42])]
    public function testIntegerReadsSignedSixtyFourBitValues(string $text, int $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s START ' . $text);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\Sequence\AlterSequenceStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(Identity\SequenceValueChange::class, $option);
        self::assertSame($expected, SequenceInvariant::integer($option->value));
    }

    public function testIntegerRejectsAnOverflow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 9223372036854775808');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::integer($value);
    }

    public function testBoundsRequiresOrderedExplicitBounds(): void
    {
        SequenceInvariant::bounds([], null, 5);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::bounds([], 5, 5);
    }

    public function testBoundsRequiresTheTypeRange(): void
    {
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::bounds(['type' => new Identity\SequenceStorage(TypeDescriptor::builtin(Dialect::PostgreSql, 'smallint'))], null, 40000);
    }

    public function testCreationUsesTheDefaultsOfADescendingSequence(): void
    {
        $down = Expression::literal(-1, Dialect::PostgreSql);
        $zero = Expression::literal(0, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $down);
        self::assertInstanceOf(Literal::class, $zero);
        SequenceInvariant::creation(['Increment' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Increment, $down), 'Start' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Start, $down)]);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::creation(['Increment' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Increment, $down), 'Start' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Start, $zero)]);
    }

    #[TestWith(['1.5'])]
    #[TestWith(['1e5'])]
    #[TestWith(['99999999999999999999'])]
    public function testIntegerRejectsEveryOtherLiteral(string $text): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ' . $text);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::integer($value);
    }

    public function testOptionsAcceptsBoundaryOwnersAndCaches(): void
    {
        $one = Expression::literal(1, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $one);
        $options = SequenceInvariant::options([new Identity\SetSequenceOwner(new QualifiedName(['t', 'c'])), new Identity\SequenceValueChange(Identity\SequenceAttribute::Cache, $one), Identity\SequenceFlag::NoMaxValue]);
        self::assertSame(['owner', 'Cache', 'MaxValue'], array_keys($options));
    }

    public function testOptionsRejectsAZeroCache(): void
    {
        $zero = Expression::literal(0, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $zero);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::options([new Identity\SequenceValueChange(Identity\SequenceAttribute::Cache, $zero)]);
    }

    public function testKeyRejectsUnlogged(): void
    {
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::key(Identity\SequenceFlag::Unlogged);
    }

    public function testBoundsAcceptsValuesOnEveryLimit(): void
    {
        $one = Expression::literal(1, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $one);
        $keyed = ['type' => new Identity\SequenceStorage(TypeDescriptor::builtin(Dialect::PostgreSql, 'smallint')), 'restart' => new Identity\RestartIdentity(null), 'Start' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Start, $one)];
        SequenceInvariant::bounds($keyed, 1, 32767);
        SequenceInvariant::bounds([], 5, null);
        self::assertSame(1, SequenceInvariant::value($keyed, Identity\SequenceAttribute::Start));
    }

    public function testBoundsRequiresTheMinimumWithinTheTypeRange(): void
    {
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::bounds(['type' => new Identity\SequenceStorage(TypeDescriptor::builtin(Dialect::PostgreSql, 'smallint'))], -40000, null);
    }

    #[TestWith([0, 1, 10])]
    #[TestWith([20, 1, 10])]
    #[TestWith([0, 1, null])]
    #[TestWith([20, null, 10])]
    public function testBoundsRequiresTheStartBetweenTheBounds(int $start, ?int $minimum, ?int $maximum): void
    {
        $value = Expression::literal($start, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::bounds(['Start' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Start, $value)], $minimum, $maximum);
    }

    public function testBoundsRequiresTheRestartBetweenTheBounds(): void
    {
        $value = Expression::literal(20, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::bounds(['restart' => new Identity\RestartIdentity($value)], 1, 10);
    }

    #[TestWith(['CREATE SEQUENCE s START 5'])]
    #[TestWith(['CREATE SEQUENCE s START 1'])]
    #[TestWith(['CREATE SEQUENCE s AS smallint START 32767'])]
    #[TestWith(['CREATE SEQUENCE s INCREMENT -1 START -1'])]
    public function testCreationAcceptsTheDefaultBounds(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\Sequence\CreateSequenceStatement::class, $statement);
        $options = SequenceInvariant::options($statement->options);
        SequenceInvariant::creation($options);
        self::assertSame($statement->options, array_values($options));
    }

    #[TestWith(['ALTER SEQUENCE s START 0'])]
    #[TestWith(['ALTER SEQUENCE s AS smallint START 40000'])]
    #[TestWith(['ALTER SEQUENCE s MINVALUE 10 INCREMENT -1'])]
    #[TestWith(['ALTER SEQUENCE s MAXVALUE -10'])]
    public function testCreationRejectsOptionsOutsideTheDefaultBounds(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\Sequence\AlterSequenceStatement::class, $statement);
        $options = SequenceInvariant::options($statement->options);
        $this->expectException(InvalidStructure::class);
        SequenceInvariant::creation($options);
    }
}
