<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Account\Authentication\SyntaxPasswords;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(SyntaxPasswords::class)]
#[Medium]
final class SyntaxPasswordsTest extends TestCase
{
    public function testStartingRefusesTheHashBeforeAFollowingSyntaxError(): void
    {
        $sql = "ALTER USER u IDENTIFIED BY PASSWORD 'text' REQUIRE";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql5744));

        self::assertEquals([[strlen('ALTER USER u IDENTIFIED BY PASSWORD '), new ParseFailure(new RejectedPasswordHash(), true)]], $notices);
    }

    public function testStartingEndsTheAlterRoleBeforeTheNextStoredStatement(): void
    {
        $sql = "CREATE PROCEDURE p() BEGIN ALTER USER u IDENTIFIED BY 'x'; CREATE USER v IDENTIFIED BY PASSWORD '*0123456789012345678901234567890123456789'; END";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql5744));

        self::assertSame([], array_values(array_filter(array_column($notices, 1), static fn ($notice): bool => $notice instanceof ParseFailure)));
    }
}
