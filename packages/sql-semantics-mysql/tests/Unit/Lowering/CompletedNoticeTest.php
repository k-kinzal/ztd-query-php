<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\CompletedNotice;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(CompletedNotice::class)]
#[Medium]
final class CompletedNoticeTest extends TestCase
{
    public function testOperandsReadsThePrimitiveNameRefusal(): void
    {
        $parser = new MySqlParser('mysql-5.7.44');
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../resources/productions/mysql-5.7.44.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql5744));
        $notices = [];
        $parser->parse('ALTER TABLE `` ADD a INT', static function (\SqlParser\Parser\Node $node) use ($lowering, &$notices): void {
            $notice = (new CompletedNotice())->operands($node, $lowering);
            if ($notice !== null) {
                $notices[] = $notice;
            }
        });

        self::assertEquals([new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName(\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Table), true)], $notices);
    }

    public function testOfDispatchesInnerRefusalBeforeBinaryCompletion(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847));
        $sql = 'SELECT BINARY CAST(1 AT LOCAL AS DATE)';
        $notices = [];
        $parser->parse($sql, static function (\SqlParser\Parser\Node $node) use ($parser, $lowering, $sql, &$notices): void {
            $notice = (new CompletedNotice())->of($node, $lowering, $parser, $sql);
            if ($notice !== null) {
                $notices[] = $notice;
            }
        });

        self::assertEquals([new ParseFailure(new NotSupportedYet('AT LOCAL')), new Deprecation(Deprecated::BinaryOperator, aborts: true)], $notices);
    }
}
