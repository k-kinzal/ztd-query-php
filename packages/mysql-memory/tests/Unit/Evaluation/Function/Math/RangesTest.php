<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Math\Ranges;
use MySqlMemory\Instance;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;

#[CoversClass(Ranges::class)]
#[Small]
final class RangesTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheFirstBound(): void
    {
        $domain = Domain::of(Resolved::integer(), false);

        self::assertSame($domain, (new Ranges(['1'], true, $domain))->domain());
    }

    public function testEvaluateAnswersTheFirstBound(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(1.5, (new Ranges([1.5, 2.0], false, Domain::of(Resolved::double(), false)))->evaluate($frame));
    }

    public function testSearchSearchesByHalvesAsTheServerDoes(): void
    {
        $sorted = new Ranges(['1', '2', '3', '4', '5', '6', '7', '8'], true, Domain::of(Resolved::integer(), false));
        $unsorted = new Ranges(['1', '1', '1', '9', '1', '1', '1', '1'], true, Domain::of(Resolved::integer(), false));

        self::assertSame([0, 5, 8, 8], [$sorted->search('0'), $sorted->search('5'), $sorted->search('9'), $unsorted->search('5')]);
    }

    public function testCompareComparesDecimalsOrDoubles(): void
    {
        self::assertSame([-1, 0], [(new Ranges([], true, Domain::of(Resolved::integer(), false)))->compare('18446744073709551614', '18446744073709551615'), (new Ranges([], false, Domain::of(Resolved::double(), false)))->compare(1.8446744073709552e19, 1.8446744073709552e19)]);
    }
}
