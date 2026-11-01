<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Declaration\Key;

#[CoversClass(Key::class)]
#[Medium]
final class KeyTest extends TestCase
{
    public function testDeterminesHoldsForAPrimaryKeyAndAUniqueKeyOfNotNullColumns(): void
    {
        $table = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT PRIMARY KEY, b INT NOT NULL UNIQUE, c INT UNIQUE)')->declarations()[0];

        self::assertSame([true, true, false], array_map(static fn (Key $key): bool => $key->determines(), $table->keys));
        self::assertSame([true, false, false], array_map(static fn (Key $key): bool => $key->primary, $table->keys));
    }
}
