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

    public function testOptionsRetainsLaterOperandsAfterAnInvalidRange(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE USER u PASSWORD_LOCK_TIME 32768 PASSWORD HISTORY 2');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\CreateUser::class, $statement);
        self::assertCount(2, $statement->options);
        self::assertSame('2', $statement->options[1]->number?->text);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange::class, $statement->diagnostics()[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $statement->warnings()[0]);
        self::assertTrue($statement->warnings()[0]->aborts);
    }

}
