<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\SyntaxStatements;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;

#[CoversClass(SyntaxStatements::class)]
#[Medium]
final class SyntaxStatementsTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.6' => [GrammarRelease::MySql5651];
        yield '5.7' => [GrammarRelease::MySql5744];
        yield '8.0' => [GrammarRelease::MySql8044];
        yield '8.4' => [GrammarRelease::MySql847];
        yield '9.1' => [GrammarRelease::MySql910];
    }

    #[DataProvider('providerReleases')]
    public function testCompletedResolvesSystemVariablesBeforeLaterMalformedInput(GrammarRelease $release): void
    {
        $sql = 'CREATE PROCEDURE d.p() BEGIN SELECT @@missing; PARTITION BROKEN; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));

        self::assertEquals([new ParseFailure(new UnknownSystemVariable('missing'), true)], array_column($notices, 1));
    }

    public function testCompletedLeavesOuterScopeReferencesToCompleteProgramAnalysis(): void
    {
        $sql = "CREATE PROCEDURE d.p(x INT) BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN SELECT x; SIGNAL c; PARTITION BROKEN; END; END";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([], $notices);
    }
}
