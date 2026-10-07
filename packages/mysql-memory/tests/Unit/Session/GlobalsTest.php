<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Globals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;
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
}
