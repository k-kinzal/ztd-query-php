<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\DeclarationPrefix;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(DeclarationPrefix::class)]
#[Medium]
final class DeclarationPrefixTest extends TestCase
{
    public function testFollowingKeepsCollationNoticeBeforeReturnTypeRefusal(): void
    {
        $sql = 'CREATE FUNCTION f() RETURNS INT COLLATE ucs2_bin RETURN 1';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen('CREATE FUNCTION f() RETURNS INT COLLATE ucs2_bin'), new LanguageProfile(GrammarRelease::MySql847));

        self::assertCount(1, $notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $notices[0][1]);
        self::assertSame(4079, $notices[0][1]->code());
    }

    public function testFollowingKeepsReturnCollationBeforeAnUnfinishedBody(): void
    {
        $sql = "CREATE FUNCTION f() RETURNS INT COLLATE utf8mb4_bin 'unfinished";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new NotSupportedYet('COLLATE with no CHARACTER SET in SP parameters, RETURNS, DECLARE'), true)], array_column($notices, 1));
    }

    public function testCompletedStopsBeforeLaterParameterWarnings(): void
    {
        $sql = 'CREATE PROCEDURE p(x INT COLLATE utf8mb4_bin, y INT(3)) BEGIN END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new NotSupportedYet('COLLATE with no CHARACTER SET in SP parameters, RETURNS, DECLARE'), true)], array_column($notices, 1));
    }

    public function testRejectedKeepsOnlyTheProblemAndInputBoundary(): void
    {
        $sql = 'CREATE PROCEDURE p(x INT COLLATE utf8mb4_bin) BEGIN END';
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847));
        $rule = new DeclarationPrefix();
        $failure = $rule->rejected($tree->find('sp_pdparam')[0], $lowering);

        self::assertInstanceOf(NotSupportedYet::class, $failure?->problem);
        self::assertSame(strpos($sql, ')'), $failure->end);
        self::assertNull($rule->rejected($tree, $lowering));
    }
}
