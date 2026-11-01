<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Account\Privilege\LegacyColumns;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(LegacyColumns::class)]
#[Medium]
final class LegacyColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerTargets(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744] as $release) {
            foreach (['GRANT', 'REVOKE'] as $verb) {
                yield $release->value . ':' . $verb => [$release, $verb];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerTargets')]
    public function testCompletedRefusesTheTargetBeforeAnUnfinishedRecipientList(GrammarRelease $release, string $verb): void
    {
        $parser = new MySqlParser($release->value);
        $profile = new LanguageProfile($release);
        $prefix = $verb . ' SELECT(a) ON d.*';
        $notices = (new SyntaxWarnings())->completed($parser, $prefix . ' TO ?', 100, $profile);
        self::assertCount(1, $notices);
        self::assertSame(strlen($prefix), $notices[0][0]);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertTrue($notices[0][1]->aborts);
        self::assertInstanceOf(RefusedColumnLevel::class, $notices[0][1]->problem);
        self::assertSame('a', $notices[0][1]->problem->privilege->columns[0]->value);
    }

    public function testCompletedRetainsTheEnclosingProgramScope(): void
    {
        $parser = new MySqlParser('mysql-5.6.51');
        $profile = new LanguageProfile(GrammarRelease::MySql5651);
        $prefix = 'CREATE PROCEDURE d.p() GRANT SELECT(a) ON *';
        $notices = (new SyntaxWarnings())->completed($parser, $prefix . ' TO ?', 100, $profile);
        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertInstanceOf(RefusedColumnLevel::class, $notices[0][1]->problem);
        self::assertSame('d', $notices[0][1]->problem->program?->schema?->value);
    }
}
