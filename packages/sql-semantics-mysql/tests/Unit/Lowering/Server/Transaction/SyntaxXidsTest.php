<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\SyntaxXids;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier;

#[CoversClass(SyntaxXids::class)]
#[Medium]
final class SyntaxXidsTest extends TestCase
{
    public function testCompletedKeepsTheFormatOperandBeforeTrailingSyntax(): void
    {
        $sql = "XA START 'g','" . str_repeat('b', 65) . "',1.5 trailing";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new RefusedXaIdentifier(81), true)], array_column($notices, 1));
    }

    public function testRejectedIdentifiesTheFollowingTokenWhenNoFormatIsWritten(): void
    {
        $sql = "XA START '" . str_repeat('g', 65) . "' JOIN";
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847));
        $rule = new SyntaxXids();

        self::assertEquals(new RefusedXaIdentifier(76, true), $rule->rejected($tree->find('xid')[0], $lowering)?->problem);
        self::assertNull($rule->rejected($tree, $lowering));
    }

    public function testRejectedKeepsFractionalFormatSpellings(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse("XA START 'g','b',1.5");
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847));

        self::assertNull((new SyntaxXids())->rejected($tree->find('xid')[0], $lowering));
    }
}
