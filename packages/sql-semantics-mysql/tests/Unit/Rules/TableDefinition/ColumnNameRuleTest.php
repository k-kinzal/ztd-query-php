<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnNameRule;

#[CoversClass(ColumnNameRule::class)]
#[Small]
final class ColumnNameRuleTest extends TestCase
{
    public function testValidRefusesEmptyNamesTrailingSpacesAndLongNames(): void
    {
        $rule = new ColumnNameRule();

        self::assertTrue($rule->valid('1+1'));
        self::assertTrue($rule->valid(str_repeat('é', 64)));
        self::assertFalse($rule->valid(''));
        self::assertFalse($rule->valid("a\t"));
        self::assertFalse($rule->valid(str_repeat('a', 65)));
        self::assertFalse($rule->valid("\0a"));
    }

    public function testLengthCountsCharacters(): void
    {
        self::assertSame(2, (new ColumnNameRule())->length('éa'));
        self::assertSame(2, (new ColumnNameRule())->length("\xFF\xFE"));
    }
}
