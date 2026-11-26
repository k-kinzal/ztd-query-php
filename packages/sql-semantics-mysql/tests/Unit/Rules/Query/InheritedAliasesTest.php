<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\Query\InheritedAliases;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(InheritedAliases::class)]
#[Small]
final class InheritedAliasesTest extends TestCase
{
    public function testNamesRetainsTheExactOwnerOfEachEffectiveAlias(): void
    {
        $one = new NumberLiteral('1');
        $two = new NumberLiteral('2');
        $query = new ParenthesizedQuery(new Select([], [
            new SelectExpression($one, new Name(' ' . str_repeat('a', 257))),
            new SelectExpression($two, new Name(" \t")),
        ]));

        $names = (new InheritedAliases())->names($query);

        self::assertSame(str_repeat('a', 256), $names[spl_object_id($one)]->value);
        self::assertSame('', $names[spl_object_id($two)]->value);
    }
}
