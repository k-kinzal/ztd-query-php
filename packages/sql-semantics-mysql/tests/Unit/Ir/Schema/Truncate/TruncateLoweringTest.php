<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Truncate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(TruncateLowering::class)]
#[Medium]
final class TruncateLoweringTest extends TestCase
{
    public function testLowerBindsTheCurrentDatabaseBeforePublication(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('logs'));
        $declarations = $semantics->context(['CREATE TABLE logs.today (id int)', 'CREATE TABLE archive.today (id int)']);
        $table = $semantics->analyze('TRUNCATE today', $declarations);

        self::assertInstanceOf(TruncateTable::class, $table);
        self::assertSame('logs', $table->target->schema->value);
        self::assertSame('today', $table->target->table->value);
    }
}
