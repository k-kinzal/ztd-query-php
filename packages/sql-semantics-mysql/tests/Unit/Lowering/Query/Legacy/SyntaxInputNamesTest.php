<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\SyntaxInputNames;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;

#[CoversClass(SyntaxInputNames::class)]
#[Medium]
final class SyntaxInputNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        yield 'legacy malformed lock' => [GrammarRelease::MySql5651, 'SELECT 1 FROM a,a FOR SHARE OF x', ['a']];
        yield 'legacy malformed predicate' => [GrammarRelease::MySql5651, 'SELECT 1 FROM a,a WHERE (', ['a']];
        yield 'legacy aliases' => [GrammarRelease::MySql5651, 'SELECT 1 FROM a AS x,b AS x FOR SHARE OF y', ['x']];
        yield 'distinct databases' => [GrammarRelease::MySql5651, 'SELECT 1 FROM d.a,e.a WHERE (', []];
        yield 'later legacy input' => [GrammarRelease::MySql5744, 'SELECT 1 FROM a,a FOR SHARE OF x', []];
        yield 'complete nested inputs' => [GrammarRelease::MySql5651, 'SELECT 1 FROM a,a,(SELECT 1 FROM b,b) AS c', ['b']];
        yield 'modern input' => [GrammarRelease::MySql847, 'SELECT 1 FROM a,a WHERE (', []];
    }

    /**
     * @param list<string> $expected The fixed repeated input names
     */
    #[DataProvider('providerInputs')]
    public function testCompletedRetainsOnlyTheLegacyInputCondition(GrammarRelease $release, string $sql, array $expected): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));
        $collisions = array_filter(array_column($notices, 1), static fn ($notice): bool => $notice instanceof ParseFailure && $notice->problem instanceof NonUniqueTable);
        self::assertSame($expected, array_values(array_map(static fn (ParseFailure $notice): ?string => $notice->problem instanceof NonUniqueTable ? $notice->problem->alias->value : null, $collisions)));
    }
    public function testUnparsedSeparatesSyntaxFailureFromSemanticCollision(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql5651);
        $parser = new MySqlParser($profile->grammar->value);
        self::assertFalse((new SyntaxInputNames())->unparsed($parser, 'SELECT 1 FROM a,a', $profile));
        self::assertTrue((new SyntaxInputNames())->unparsed($parser, 'SELECT 1 FROM a,a WHERE (', $profile));
        self::assertFalse((new SyntaxInputNames())->unparsed(new MySqlParser('mysql-8.4.7'), 'SELECT 1 FROM a,a WHERE (', new LanguageProfile(GrammarRelease::MySql847)));
    }

}
