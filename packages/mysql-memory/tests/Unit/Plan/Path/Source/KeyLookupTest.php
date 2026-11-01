<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\KeyLookup;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyLookup::class)]
#[Small]
final class KeyLookupTest extends TestCase
{
    public function testWidthPreservesInvisibleColumnsAndTheLockableTableOccurrence(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, hidden INT INVISIBLE)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $scan = new TableScan($table);
        $domain = Domain::integer();
        $lookup = new KeyLookup($scan, [[0, new Constant($domain, 1), Comparator::of($domain, $domain, '=', $session->settings()->connectionCollation)]]);

        self::assertSame(2, $lookup->width());
        self::assertSame($scan, $lookup->input);
    }
}
