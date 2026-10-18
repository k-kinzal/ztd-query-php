<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\SyntaxKeywords;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(SyntaxKeywords::class)]
#[Medium]
final class SyntaxKeywordsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Deprecated>}>
     */
    public static function providerKeywords(): iterable
    {
        yield 'repeated cache items' => ['FLUSH QUERY CACHE, HOSTS, QUERY CACHE', [Deprecated::FlushQueryCache, Deprecated::FlushQueryCache]];
        yield 'legacy lock' => ['LOCK TABLES t LOW_PRIORITY WRITE', [Deprecated::LowPriorityWrite]];
        yield 'database upgrade' => ['ALTER DATABASE d UPGRADE DATA DIRECTORY NAME', [Deprecated::UpgradeDatabaseName]];
    }

    /**
     * @param list<Deprecated> $expected Notices in written order
     */
    #[DataProvider('providerKeywords')]
    public function testCompletedRetainsEachAdministrativeNoticeInWrittenOrder(string $sql, array $expected): void
    {
        $parser = new MySqlParser('mysql-5.7.44');
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-5.7.44.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql5744));
        $notices = [];
        $parser->parse($sql, static function (\SqlParser\Parser\Node $node) use ($lowering, &$notices): void {
            $notice = (new SyntaxKeywords())->completed($node, $lowering);
            if ($notice !== null) {
                $notices[] = $notice;
            }
        });

        self::assertSame($expected, $notices);
    }
    public function testCompletedKeepsTheEventStatusNoticeBeforeAnIncompleteBody(): void
    {
        $sql = 'ALTER EVENT e DISABLE ON SLAVE DO SELECT !1, ?';
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql) - 1, new LanguageProfile(GrammarRelease::MySql847));
        self::assertSame([Deprecated::DisableOnSlave], array_column($notices, 1));
    }
}
