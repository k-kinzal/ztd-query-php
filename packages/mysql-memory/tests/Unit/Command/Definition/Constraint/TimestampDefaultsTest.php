<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\TimestampDefaults;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(TimestampDefaults::class)]
#[Medium]
final class TimestampDefaultsTest extends TestCase
{
    public function testFirstCountsANullableTimestampBeforeARequiredOne(): void
    {
        $create = (new Instance('5.7.44'))->connect()->analyze('CREATE TABLE t(a TIMESTAMP NULL, b TIMESTAMP)');
        self::assertInstanceOf(CreateTable::class, $create);
        self::assertInstanceOf(ColumnDefinition::class, $create->elements[0]);
        self::assertInstanceOf(ColumnDefinition::class, $create->elements[1]);

        self::assertTrue((new TimestampDefaults())->first($create->elements[0], $create));
        self::assertFalse((new TimestampDefaults())->first($create->elements[1], $create));
    }

    public function testApplyAddsTheClockToTheFirstTimestampAndZeroToLaterOnes(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect();
        $session->query("USE d; SET sql_mode=''; CREATE TABLE t(a TIMESTAMP(2), b TIMESTAMP)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertTrue($table->definition->columns[0]->default->now);
        self::assertTrue($table->definition->columns[0]->onUpdateNow);
        self::assertSame('CURRENT_TIMESTAMP(2)', $table->definition->columns[0]->default->text);
        self::assertSame('0000-00-00 00:00:00', $table->definition->columns[1]->default->value);
        self::assertFalse($table->definition->columns[1]->onUpdateNow);
    }

    public function testApplyRejectsAnImplicitZeroDefaultUnderNoZeroDate(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect();
        $session->query("USE d; SET sql_mode='NO_ZERO_DATE'");
        $this->expectExceptionCode(1067);

        $session->query('CREATE TABLE t(a TIMESTAMP NULL, b TIMESTAMP)');
    }
}
