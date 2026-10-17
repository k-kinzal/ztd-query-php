<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Globals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability;

#[CoversClass(Globals::class)]
#[Small]
final class GlobalsTest extends TestCase
{
    public function testValueAnswersTheDefaultOfAVariableNeverSet(): void
    {
        $definition = new Definition('max_allowed_packet', Reach::Both, ValueShape::Unsigned, 67108864, Writability::GlobalOnly, 1024, 1073741824, Domain::integer());

        self::assertSame(67108864, (new Globals())->value($definition));
    }

    public function testValueAnswersTheValueTheServerStartedWith(): void
    {
        $definition = new Definition('max_allowed_packet', Reach::Both, ValueShape::Unsigned, 67108864, Writability::GlobalOnly, 1024, 1073741824, Domain::integer());

        self::assertSame(1024, (new Globals(['max_allowed_packet' => 1024]))->value($definition));
    }

    public function testSetChangesTheGlobalValue(): void
    {
        $definition = new Definition('autocommit', Reach::Both, ValueShape::Boolean, 'ON', Writability::Writable, null, null, Domain::integer());
        $globals = new Globals();
        $globals->set($definition, 'OFF');

        self::assertSame('OFF', $globals->value($definition));
        self::assertSame(['autocommit' => 'OFF'], $globals->values);
    }

    public function testSetOfAStatementChangesTheValueNewSessionsStartWith(): void
    {
        $instance = new Instance();
        $instance->connect()->query('SET GLOBAL div_precision_increment = 6');
        $result = $instance->connect()->query('SELECT @@div_precision_increment, @@GLOBAL.div_precision_increment')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['6', '6']], $result->rows);
        self::assertSame(['div_precision_increment' => 6], $instance->globals->values);
    }

    public function testValueAnswersANullValue(): void
    {
        $definition = new Definition('character_set_results', Reach::Both, ValueShape::Text, 'utf8mb4', Writability::Writable, null, null, Domain::integer());
        $globals = new Globals();
        $globals->set($definition, null);

        self::assertNull($globals->value($definition));
    }

    public function testValueKeepsNullDefaultsDistinctFromExplicitEmptyValues(): void
    {
        $definition = SystemVariables::of(GrammarRelease::MySql847)->find('innodb_monitor_reset');
        self::assertNotNull($definition);

        self::assertNull((new Globals())->value($definition));
        self::assertNull((new Globals(['innodb_monitor_reset' => null]))->value($definition));
        self::assertSame('', (new Globals(['innodb_monitor_reset' => '']))->value($definition));
        self::assertSame('latch', (new Globals(['innodb_monitor_reset' => 'latch']))->value($definition));
    }

    public function testCachedAnswersZeroForAKeyCacheThatDoesNotExist(): void
    {
        $definition = new Definition('key_cache_block_size', Reach::Global, ValueShape::Unsigned, 1024, Writability::GlobalOnly, 512, 16384, Domain::integer());

        self::assertSame(0, (new Globals())->cached('x', $definition));
    }

    public function testCachedAnswersTheGlobalValueForTheDefaultKeyCache(): void
    {
        $definition = new Definition('key_cache_block_size', Reach::Global, ValueShape::Unsigned, 1024, Writability::GlobalOnly, 512, 16384, Domain::integer());

        self::assertSame(2048, (new Globals(['key_cache_block_size' => 2048]))->cached('default', $definition));
    }

    public function testCacheCreatesAKeyCacheWhoseOtherParametersHoldTheirDefaults(): void
    {
        $block = new Definition('key_cache_block_size', Reach::Global, ValueShape::Unsigned, 1024, Writability::GlobalOnly, 512, 16384, Domain::integer());
        $buffer = new Definition('key_buffer_size', Reach::Global, ValueShape::Unsigned, 8388608, Writability::GlobalOnly, 0, null, Domain::integer());
        $limit = new Definition('key_cache_division_limit', Reach::Global, ValueShape::Unsigned, 100, Writability::GlobalOnly, 1, 100, Domain::integer());
        $globals = new Globals();
        $globals->cache('kc', $block, 2048);

        self::assertSame([2048, 0, 100, 0, 1024], [$globals->cached('kc', $block), $globals->cached('kc', $buffer), $globals->cached('kc', $limit), $globals->cached('KC', $block), $globals->value($block)]);
    }

    public function testCacheSetsTheGlobalValueForTheDefaultKeyCache(): void
    {
        $block = new Definition('key_cache_block_size', Reach::Global, ValueShape::Unsigned, 1024, Writability::GlobalOnly, 512, 16384, Domain::integer());
        $globals = new Globals();
        $globals->cache('default', $block, 2048);

        self::assertSame(['key_cache_block_size' => 2048], $globals->values);
    }
}
