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
}
