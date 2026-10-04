<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption;

#[CoversNothing]
#[Medium]
final class WithOptionTest extends TestCase
{
    public function testBothOptionsImplementTheInterface(): void
    {
        $grant = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('GRANT SELECT ON *.* TO u WITH GRANT OPTION MAX_QUERIES_PER_HOUR 1');

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertContainsOnlyInstancesOf(WithOption::class, $grant->statement->options);
        self::assertInstanceOf(GrantOptionRight::class, $grant->statement->options[0]);
        self::assertInstanceOf(ResourceLimit::class, $grant->statement->options[1]);
    }
}
