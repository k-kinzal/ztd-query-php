<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\ClientEntry;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused;

#[CoversClass(ClientEntry::class)]
#[Medium]
final class ClientEntryTest extends TestCase
{
    public function testUnparsedInspectsEntriesBeforeSeveralInvalidBytes(): void
    {
        $parser = new MySqlParser('mysql-5.7.44');
        $profile = new LanguageProfile(GrammarRelease::MySql5744);

        self::assertNull((new ClientEntry())->unparsed($parser, "abc]\x1e", $profile));
        self::assertSame(9, (new ClientEntry())->unparsed($parser, "PARTITION ]\x1e", $profile)?->end);
        self::assertSame(38, (new ClientEntry())->nested($parser, "CREATE PROCEDURE d.p() BEGIN PARTITION ]\x1e", $profile)?->end);
    }

    public function testGeneratedKeepsOnlyTheCompletedEntryPositions(): void
    {
        $parser = new MySqlParser('mysql-5.7.44');
        $sql = '/* prefix */ PARSE_GCOL_EXPR(missing)';
        $failure = (new ClientEntry())->generated($parser->parse($sql)->find('parse_gcol_expr')[0]);

        self::assertNotNull($failure);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry::class, $failure->problem);
        self::assertSame(13, $failure->problem->offset);
        self::assertSame(strlen($sql), $failure->end);
        self::assertTrue($failure->aborts);
        self::assertNull((new ClientEntry())->generated($parser->parse('SELECT 1')));
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerLegacyReleases(): iterable
    {
        yield '5.6' => [GrammarRelease::MySql5651];
        yield '5.7' => [GrammarRelease::MySql5744];
    }

    #[DataProvider('providerLegacyReleases')]
    public function testPartitionRefusesTheKeywordBeforeItsBody(GrammarRelease $release): void
    {
        $parser = new MySqlParser($release->value);
        $profile = new LanguageProfile($release);
        $token = $parser->tokenize('/* before */ PARTITION BY HASH(a)')[0];
        $failure = (new ClientEntry())->partition($token, $profile);

        self::assertNotNull($failure);
        self::assertInstanceOf(PartitionEntryRefused::class, $failure->problem);
        self::assertSame(strlen('/* before */ PARTITION'), $failure->end);
        self::assertTrue($failure->aborts);
        self::assertNull((new ClientEntry())->partition($parser->tokenize('ALTER TABLE t PARTITION BY HASH(a)')[0], $profile));
        self::assertNull((new ClientEntry())->partition(null, $profile));
    }

    #[DataProvider('providerLegacyReleases')]
    public function testUnparsedRetainsTheKeywordBeforeALexicalFailure(GrammarRelease $release): void
    {
        $parser = new MySqlParser($release->value);
        $failure = (new ClientEntry())->unparsed($parser, "PARTITION BY HASH('unclosed", new LanguageProfile($release));

        self::assertNotNull($failure);
        self::assertSame(9, $failure->end);
    }

    public function testPartitionDoesNotIntroduceTheLegacyEntryInModernProfiles(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');
        $profile = new LanguageProfile(GrammarRelease::MySql847);

        self::assertNull((new ClientEntry())->partition($parser->tokenize('PARTITION BY HASH(a)')[0], $profile));
        self::assertNull((new ClientEntry())->unparsed($parser, 'PARTITION BROKEN', $profile));
        self::assertNull((new ClientEntry())->nested($parser, 'CREATE PROCEDURE d.p() PARTITION BROKEN', $profile));
    }

    #[DataProvider('providerLegacyReleases')]
    public function testNestedDistinguishesClientEntriesFromTableClauses(GrammarRelease $release): void
    {
        $parser = new MySqlParser($release->value);
        $profile = new LanguageProfile($release);
        $prefix = 'CREATE PROCEDURE d.p() BEGIN SELECT 1; PARTITION';
        $failure = (new ClientEntry())->nested($parser, $prefix . ' BROKEN; END', $profile);

        self::assertNotNull($failure);
        self::assertSame(strlen($prefix), $failure->end);
        self::assertNull((new ClientEntry())->nested($parser, 'CREATE TABLE t(a INT) PARTITION BROKEN', $profile));
        self::assertNull((new ClientEntry())->nested($parser, 'CREATE PROCEDURE d.p() ALTER TABLE t PARTITION BROKEN', $profile));
        self::assertNull((new ClientEntry())->nested($parser, 'CREATE PROCEDURE d.p() SELECT FROM PARTITION BROKEN', $profile));
    }
}
