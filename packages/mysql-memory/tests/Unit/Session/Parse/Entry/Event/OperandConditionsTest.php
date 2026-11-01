<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Event;

use MySqlMemory\Session\Parse\Entry\Event\OperandConditions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth;

#[CoversClass(OperandConditions::class)]
#[Small]
final class OperandConditionsTest extends TestCase
{
    /**
     * @return iterable<string, array{Clause, bool, bool, list<int>}>
     */
    public static function providerConditions(): iterable
    {
        yield 'legacy AT row' => [Clause::At, true, true, [1241, 1525]];
        yield 'legacy STARTS scalar' => [Clause::Starts, false, true, [1525]];
        yield 'legacy INTERVAL row' => [Clause::Interval, true, true, [1542]];
        yield 'legacy ENDS row' => [Clause::Ends, true, true, [1543]];
        yield 'modern row' => [Clause::At, true, false, []];
    }

    /**
     * @param list<int> $following
     */
    #[DataProvider('providerConditions')]
    public function testErrorPreservesOrderedConversionConditions(Clause $clause, bool $row, bool $legacy, array $following): void
    {
        $error = (new OperandConditions())->error(new OperandWidth(new OperandColumns(1, 2), $clause, $row), $legacy);
        self::assertSame(1241, $error->getCode());
        self::assertSame($following, array_column($error->following, 0));
    }
}
