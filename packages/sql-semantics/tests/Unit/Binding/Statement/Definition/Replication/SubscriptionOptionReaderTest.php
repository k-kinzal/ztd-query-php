<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Replication\SubscriptionOptionReader;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionElement;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SubscriptionOptionReader::class)]
#[Medium]
final class SubscriptionOptionReaderTest extends TestCase
{
    #[TestWith(['ALTER SUBSCRIPTION s SET (enabled)'])]
    #[TestWith(['ALTER SUBSCRIPTION s SET (binary, binary)'])]
    #[TestWith(['ALTER SUBSCRIPTION s REFRESH PUBLICATION WITH (refresh)'])]
    #[TestWith(['ALTER SUBSCRIPTION s SKIP (copy_data)'])]
    public function testReadRejectsAnOptionTheCommandDoesNotAccept(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionAttribute->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith(['binary = maybe'])]
    #[TestWith(["origin = 'local'"])]
    #[TestWith(["slot_name = 'Upper'"])]
    #[TestWith(['slot_name'])]
    #[TestWith(['synchronous_commit = always'])]
    #[TestWith(['streaming = 2'])]
    public function testReadRejectsAMistypedValue(string $option): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SET (' . $option . ')');
    }

    #[TestWith(['binary', true])]
    #[TestWith(['binary = 0', false])]
    #[TestWith(["binary = 'On'", true])]
    public function testBooleanReadsAnOmittedValueAsTrue(string $option, bool $value): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SET (' . $option . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($value, $statement->options->binary);
    }

    public function testTextRequiresAValue(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SET (origin)');
    }

    #[TestWith(['my_slot', 'my_slot'])]
    #[TestWith(["'my_slot'", 'my_slot'])]
    public function testSlotNamesASlot(string $spelling, string $name): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SET (slot_name = ' . $spelling . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($name, $statement->options->slotName);
    }

    #[TestWith(["'REMOTE_APPLY'", Operand\SynchronousCommit::RemoteApply])]
    #[TestWith(['no', Operand\SynchronousCommit::Off])]
    public function testSynchronousReadsEveryLevelSpelling(string $spelling, Operand\SynchronousCommit $level): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SET (synchronous_commit = ' . $spelling . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($level, $statement->options->synchronousCommit);
    }

    #[TestWith(['streaming', Operand\StreamingMode::On])]
    #[TestWith(['streaming = parallel', Operand\StreamingMode::Parallel])]
    #[TestWith(["streaming = 'off'", Operand\StreamingMode::Off])]
    public function testStreamingReadsBooleansAndParallel(string $option, Operand\StreamingMode $mode): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SET (' . $option . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($mode, $statement->options->streaming);
    }

    #[TestWith(["'0/14c0378'", '0/14C0378'])]
    #[TestWith(["'00000001/0000000A'", '1/A'])]
    #[TestWith(['NONE', null])]
    public function testLsnCanonicalizesThePosition(string $spelling, ?string $lsn): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SKIP (lsn = ' . $spelling . ')');
        self::assertInstanceOf(Statement\SkipSubscriptionTransactionStatement::class, $statement);
        self::assertSame($lsn, $statement->lsn);
    }

    #[TestWith(["'0/0'"])]
    #[TestWith(["'123456789/1'"])]
    #[TestWith(["'NONE'"])]
    public function testLsnRejectsAnInvalidPosition(string $spelling): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SKIP (lsn = ' . $spelling . ')');
    }

    #[TestWith(['true', Operand\SynchronousCommit::On])]
    #[TestWith(['yes', Operand\SynchronousCommit::On])]
    #[TestWith(['1', Operand\SynchronousCommit::On])]
    #[TestWith(['on', Operand\SynchronousCommit::On])]
    #[TestWith(['false', Operand\SynchronousCommit::Off])]
    #[TestWith(['no', Operand\SynchronousCommit::Off])]
    #[TestWith(['0', Operand\SynchronousCommit::Off])]
    #[TestWith(['off', Operand\SynchronousCommit::Off])]
    #[TestWith(['local', Operand\SynchronousCommit::Local])]
    #[TestWith(['remote_write', Operand\SynchronousCommit::RemoteWrite])]
    public function testSynchronousMapsEachSpelling(string $value, Operand\SynchronousCommit $level): void
    {
        self::assertSame($level, SubscriptionOptionReader::synchronous($value, new Node('definition', 0, [])));
    }

    public function testReadWithoutDefinitionLeavesEveryOptionUnset(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        self::assertEquals(new Operand\SubscriptionOptions(), SubscriptionOptionReader::read(null, [], $context));
    }

    public function testReadKeepsAnOriginFilter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SET (origin = any)');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame(Operand\OriginFilter::Any, $statement->options->origin);
    }

    public function testStreamingReadsAnUpperCaseParallel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION s SET (streaming = 'PARALLEL')");
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame(Operand\StreamingMode::Parallel, $statement->options->streaming);
    }

    public function testLsnKeepsANonzeroHighHalf(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION s SKIP (lsn = '1/0')");
        self::assertInstanceOf(Statement\SkipSubscriptionTransactionStatement::class, $statement);
        self::assertSame('1/0', $statement->lsn);
    }

    #[TestWith(["'1/2x'"])]
    #[TestWith(["E'1/2\\n'"])]
    public function testLsnRejectsTrailingCharacters(string $spelling): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s SKIP (lsn = ' . $spelling . ')');
    }

    public function testLsnRejectsARepeatedPosition(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION s SKIP (lsn = '0/1', lsn = '0/2')");
    }

    public function testElementReadersReadParsedOptions(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list(Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("ALTER SUBSCRIPTION s SET (binary, slot_name = none, origin = 'Any', streaming = parallel)"), ['definition'])[0], $context);
        self::assertTrue(SubscriptionOptionReader::boolean($elements[0], $context));
        self::assertSame(Operand\NoSlot::None, SubscriptionOptionReader::slot($elements[1], $context));
        self::assertSame('Any', SubscriptionOptionReader::text($elements[2], $context));
        self::assertSame(Operand\StreamingMode::Parallel, SubscriptionOptionReader::streaming($elements[3], $context));
    }
}
