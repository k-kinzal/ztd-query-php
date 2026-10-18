<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\ViewPrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewPrefix::class)]
#[Medium]
final class ViewPrefixTest extends TestCase
{
    public function testDatabasePrecedesARefusedBodyAfterACompletedViewName(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'ALTER VIEW v AS VALUES ROW(1)';
        self::assertSame(1046, (new ViewPrefix())->database($sql, strlen($sql), $session)?->getCode());
        self::assertNull((new ViewPrefix())->database('ALTER VIEW d.v AS VALUES ROW(1)', 100, $session));
        self::assertNull((new ViewPrefix())->database('SELECT 1', 100, $session));
    }
}
