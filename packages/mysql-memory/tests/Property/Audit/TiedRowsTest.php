<?php

declare(strict_types=1);

namespace Tests\Property\Audit;

use Fuzz\Structure\Audit\Result\TiedRows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Ensures tie comparison preserves all observable distinctions outside tied order.
 */
#[CoversClass(TiedRows::class)]
#[Small]
final class TiedRowsTest extends TestCase
{
    /**
     * Only the order of equal-key rows changes in a complete observation.
     */
    public function testComparableRetainsConditionsMetadataAndDuplicateRows(): void
    {
        $one = ['results' => [['columns' => ['a', 'b'], 'rows' => [['2', '3'], ['1', '3'], ['1', '3']]]], 'warnings' => [['Warning', '1', 'x']]];
        $two = ['results' => [['columns' => ['a', 'b'], 'rows' => [['1', '3'], ['1', '3'], ['2', '3']]]], 'warnings' => [['Warning', '1', 'x']]];
        self::assertSame($two, (new TiedRows())->comparable($one, [1]));
        self::assertSame(['error' => 1054], (new TiedRows())->comparable(['error' => 1054], [1]));
    }

    /**
     * Reversed distinct key groups and later repeated keys remain distinguishable.
     */
    public function testRowsKeepsDistinctKeyGroupsInTheirObservedOrder(): void
    {
        $rows = [['2', '3'], ['1', '3'], ['4', '2'], ['3', '2'], ['0', '3']];
        self::assertSame([['1', '3'], ['2', '3'], ['3', '2'], ['4', '2'], ['0', '3']], (new TiedRows())->rows($rows, [1]));
        self::assertSame($rows, (new TiedRows())->rows($rows, [1, 0]));
    }

    /**
     * A caller cannot normalize by an ordering expression absent from the result.
     */
    public function testRowsRejectsAnUnobservedKey(): void
    {
        $this->expectExceptionMessage('Every ordering key must be present in the observed row.');
        (new TiedRows())->rows([['1']], [1]);
    }
}
