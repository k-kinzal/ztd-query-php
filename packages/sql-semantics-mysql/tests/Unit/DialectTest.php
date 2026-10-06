<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(Dialect::class)]
#[Medium]
final class DialectTest extends TestCase
{
    public function testDatabaseNamesTheGrammarFamily(): void
    {
        self::assertSame('mysql', Dialect::MySql->database());
        self::assertSame('mysql', (new Semantics(Dialect::MySql))->profile()->grammar->database());
        self::assertSame('SELECT a FROM t', (new Semantics(Dialect::MySql))->analyze('select a from t')->toString());
    }
}
