<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfiles;

#[CoversClass(ShowProfiles::class)]
#[Medium]
final class ShowProfilesTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW PROFILES');
        self::assertInstanceOf(ShowProfiles::class, $show->statement);
        self::assertSame('Query_ID', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW PROFILES', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW PROFILES')->toString());
    }
}
