<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Instance;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Problem\Location\SetEncodings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SetEncodings::class)]
#[Medium]
final class SetEncodingsTest extends TestCase
{
    public function testLocatedKeepsTheClientCheckAtItsLegacyAssignment(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $item = new SetNames(new CharsetName(new Name('ucs2')));
        $statement = new SetVariables([$item]);
        $locator = (new Locator())->statement($statement);
        $located = array_values((new SetEncodings())->located($statement, $locator, $session, []));
        self::assertCount(1, $located);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $located[0][0]);
        self::assertSame(1231, $located[0][0]->getCode());
        self::assertSame($locator->place($item), $located[0][1]);
        self::assertSame([], (new SetEncodings())->located($statement, $locator, (new Instance('8.4.7'))->connect(), []));
    }
}
