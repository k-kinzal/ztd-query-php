<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;

#[CoversClass(MisplacedPrivilege::class)]
#[Small]
final class MisplacedPrivilegeTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('EXECUTE cannot be granted at object t (ER_ILLEGAL_GRANT_FOR_TABLE).', (new MisplacedPrivilege('EXECUTE', 'object t', 'ER_ILLEGAL_GRANT_FOR_TABLE'))->message());
    }
}
