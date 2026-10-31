<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Account\SyntaxRoles;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(SyntaxRoles::class)]
#[Medium]
final class SyntaxRolesTest extends TestCase
{
    public function testDeprecationPrecedesTheRoleOperandFailure(): void
    {
        $sql = 'GRANT SUPER TO u';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::SuperPrivilege, $notices[0][1]);
        self::assertInstanceOf(ParseFailure::class, $notices[1][1]);
    }

    public function testCompletedRetainsEachOperandProblemInOrder(): void
    {
        $sql = "GRANT 'r'@'bad@host', SELECT TO u";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame(['The account host contains an illegal at sign.', 'SELECT is not a role: illegal authorization identifier.'], array_map(static fn (array $notice): string => $notice[1] instanceof ParseFailure ? $notice[1]->message() : 'unexpected notice', $notices));
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertFalse($notices[0][1]->endsProgramStatement);
    }
}
