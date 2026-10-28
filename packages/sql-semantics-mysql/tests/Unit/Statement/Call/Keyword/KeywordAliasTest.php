<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Keyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;

#[CoversClass(KeywordAlias::class)]
#[Medium]
final class KeywordAliasTest extends TestCase
{
    public function testFunctionRetainsTheCanonicalIdentity(): void
    {
        self::assertSame([
            KeywordFunction::Database, KeywordFunction::User, KeywordFunction::User,
            KeywordFunction::Substring, KeywordFunction::Substring, KeywordFunction::GeometryCollection,
        ], array_map(static fn (KeywordAlias $alias): KeywordFunction => $alias->function(), KeywordAlias::cases()));
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerQueries(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['SCHEMA()', 'SESSION_USER()', 'SYSTEM_USER()', "SUBSTR('abcd', 2, 1)", "MID('abcd', 2)"] as $call) {
                yield "$release->value/$call" => [$release, 'SELECT ' . $call];
            }
            if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                yield "$release->value/geometry" => [$release, 'SELECT GEOMCOLLECTION()'];
                yield "$release->value/kill" => [$release, 'KILL USER() = USER() MEMBER(GEOMCOLLECTION()) IS TRUE'];
                foreach (['SESSION_USER', 'SYSTEM_USER'] as $name) {
                    yield "$release->value/account/$name" => [$release, "ALTER USER $name() DISCARD OLD PASSWORD"];
                }
            }
        }
    }

    #[DataProvider('providerQueries')]
    public function testRenderRetainsKeywordSynonymsInTheirSqlRoles(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql))->toString()));
    }
}
