<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Account\AccountChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute;

#[CoversClass(AccountChecks::class)]
#[Medium]
final class AccountChecksTest extends TestCase
{
    public function testCommentAcceptsOnlyAJsonObject(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertInstanceOf(InvalidUserAttribute::class, $semantics->analyze("ALTER USER u ATTRIBUTE '1'")->diagnostics()[0]);
        self::assertSame([], $semantics->analyze("ALTER USER u ATTRIBUTE '{\"a\": 1}'")->diagnostics());
    }

}
