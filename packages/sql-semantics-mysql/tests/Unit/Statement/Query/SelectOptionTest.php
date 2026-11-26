<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SelectOption::class)]
#[Medium]
final class SelectOptionTest extends TestCase
{
    public function testCasesSpellTheModifierKeywords(): void
    {
        self::assertSame(['ALL', 'DISTINCT', 'STRAIGHT_JOIN', 'HIGH_PRIORITY', 'SQL_SMALL_RESULT', 'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'SQL_CACHE'], array_column(SelectOption::cases(), 'value'));
    }

    public function testModifiersKeepTheirWrittenOrder(): void
    {
        $operation = (new Inspection('mysql-5.7.44'))->inspect('SELECT SQL_CACHE HIGH_PRIORITY DISTINCT a FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([SelectOption::Cache, SelectOption::HighPriority, SelectOption::Distinct], $operation->statement->options);
        self::assertSame('SELECT SQL_CACHE HIGH_PRIORITY DISTINCT a FROM t', $operation->toString());
    }
}
