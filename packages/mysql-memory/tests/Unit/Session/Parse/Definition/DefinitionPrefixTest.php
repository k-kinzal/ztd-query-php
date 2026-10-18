<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\DefinitionPrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefinitionPrefix::class)]
#[Medium]
final class DefinitionPrefixTest extends TestCase
{
    public function testDatabasePrecedesARefusedBodyAfterACompletedViewName(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'ALTER VIEW v AS VALUES ROW(1)';
        self::assertSame(1046, (new DefinitionPrefix())->database($sql, strlen($sql), $session)?->getCode());
        self::assertNull((new DefinitionPrefix())->database('ALTER VIEW d.v AS VALUES ROW(1)', 100, $session));
        self::assertNull((new DefinitionPrefix())->database('SELECT 1', 100, $session));
    }
    public function testBeforeRetainsTheLegacyIndexTargetBeforeUnsupportedKeySyntax(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'CREATE INDEX i ON t (((SELECT 1)))';
        self::assertSame(1046, (new DefinitionPrefix())->before($sql, strlen($sql), $session)?->getCode());
        self::assertNull((new DefinitionPrefix())->before('CREATE INDEX i ON d.t (((SELECT 1)))', 100, $session));
    }

}
