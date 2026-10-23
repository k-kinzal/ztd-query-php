<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Name\AliasRule;
use SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias;

#[CoversClass(AliasRule::class)]
#[Medium]
final class AliasRuleTest extends TestCase
{
    public function testValueDistinguishesAnAggregateReferenceFromAForwardReference(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT SUM(1) AS x, (SELECT x)');
        $problem = $operation->facts->diagnostics[0];

        self::assertInstanceOf(InvalidProjectionAlias::class, $problem);
        self::assertSame(AliasRule::Aggregate, $problem->rule);
        self::assertSame('reference to group function', $problem->rule->value);
    }
}
