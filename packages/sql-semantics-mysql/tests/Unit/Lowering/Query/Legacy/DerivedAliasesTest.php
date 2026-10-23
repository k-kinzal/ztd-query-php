<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\DerivedAliases;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(DerivedAliases::class)]
#[Medium]
final class DerivedAliasesTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string, bool}>
     */
    public static function providerAliases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744] as $release) {
            foreach (['SELECT * FROM (SELECT 1)' => true, 'SELECT * FROM ((SELECT 1))' => true, 'SELECT * FROM ((SELECT 1)) d' => false, 'SELECT * FROM (SELECT 1) AS ``' => false, 'SELECT * FROM ((SELECT 1) x)' => false, 'SELECT * FROM t' => false] as $sql => $refused) {
                yield $release->value . ' ' . $sql => [$release, $sql, $refused];
            }
        }
    }

    #[DataProvider('providerAliases')]
    public function testRejectedDistinguishesQueryParenthesesFromRelations(GrammarRelease $release, string $sql, bool $refused): void
    {
        $platform = new Platform();
        $profile = new LanguageProfile($release);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse($sql);
        $failure = (new DerivedAliases())->rejected($tree->find('join_table_list')[0], $lowering);

        self::assertSame($refused, $failure !== null);
        self::assertNull((new DerivedAliases())->rejected($tree, $lowering));
    }
}
