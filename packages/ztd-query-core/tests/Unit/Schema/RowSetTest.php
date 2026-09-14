<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use ZtdQuery\Schema\RowSet;

#[CoversClass(RowSet::class)]
final class RowSetTest extends TestCase
{
    public function testRetainsRowKeysAndOpaqueValues(): void
    {
        $object = new stdClass();
        $rows = [7 => ['payload' => ['value' => $object]], 12 => ['payload' => null]];
        $snapshot = new RowSet($rows);
        $rows[7]['payload'] = 'changed';

        self::assertSame([7 => ['payload' => ['value' => $object]], 12 => ['payload' => null]], $snapshot->rows);
    }

    public function testStartsWithAnEmptySnapshot(): void
    {
        self::assertSame([], (new RowSet())->rows);
    }
}
