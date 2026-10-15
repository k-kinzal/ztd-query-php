<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Account\SyntaxPrivileges;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;

#[CoversClass(SyntaxPrivileges::class)]
#[Medium]
final class SyntaxPrivilegesTest extends TestCase
{
    public function testCompletedRetainsOnlyTheFirstPrivilegeRefusal(): void
    {
        $sql = 'GRANT r@h,s@h ON *.* TO u';
        $failure = (new SyntaxWarnings())->rejected((new MySqlParser())->parse($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertNotNull($failure);
        self::assertInstanceOf(RoleOrPrivilegeMismatch::class, $failure->problem);
        self::assertSame('r@h', $failure->problem->item);
        self::assertSame(6, $failure->problem->offset);
        self::assertTrue($failure->aborts);
    }

    public function testDatabaseRetainsTheTargetBoundaryWithoutTheInput(): void
    {
        $reader = new SyntaxPrivileges();
        $parser = new MySqlParser();
        $profile = new LanguageProfile(GrammarRelease::MySql847);

        self::assertSame(20, $reader->database($parser, 'GRANT r@h ON TABLE * TO u', 100, $profile));
        self::assertNull($reader->database($parser, 'GRANT r@h ON d.* TO u', 100, $profile));
        self::assertNull($reader->database($parser, 'CREATE PROCEDURE d.p() GRANT r@h ON * TO u', 100, $profile));
    }
}
