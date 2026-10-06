<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Level;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable;

#[CoversClass(AbsentGrantTable::class)]
#[Medium]
final class AbsentGrantTableTest extends TestCase
{
    public function testGrantOfCreateAcceptsAMissingTable(): void
    {
        $grant = (new Semantics(Dialect::MySql))->analyze('GRANT CREATE, SELECT ON shop.t TO u', []);

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertInstanceOf(AbsentGrantTable::class, $grant->facts->relation($grant->statement->level)->table);
        self::assertSame([], $grant->facts->diagnostics);
    }
}
