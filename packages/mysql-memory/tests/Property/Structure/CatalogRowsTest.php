<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Audit\CatalogRows;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;

/**
 * Unordered catalog comparison preserves multiplicity and diagnostic order.
 */
#[CoversNothing]
#[Small]
final class CatalogRowsTest extends TestCase
{
    /**
     * Only the selected catalogs ignore row order; duplicate or changed rows still differ.
     */
    public function testMultisetComparisonRetainsAllOtherObservationFields(): void
    {
        $first = ['results' => [['columns' => ['metadata'], 'rows' => [['b'], ['a'], ['a']]]], 'warnings' => [['first'], ['second']]];
        $second = $first;
        $second['results'][0]['rows'] = [['a'], ['b'], ['a']];
        $missing = $first;
        $missing['results'][0]['rows'] = [['a'], ['b']];
        $catalog = new ShowCharacterSet();
        $rows = new CatalogRows();

        self::assertSame($rows->comparable($catalog, $first), $rows->comparable($catalog, $second));
        self::assertNotSame($rows->comparable($catalog, $first), $rows->comparable($catalog, $missing));
        self::assertSame($first, $rows->comparable(new ShowWarnings(), $first));
        self::assertSame($first['warnings'], $rows->comparable($catalog, $first)['warnings']);
    }
}
