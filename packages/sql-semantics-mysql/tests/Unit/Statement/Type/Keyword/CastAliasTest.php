<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Keyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias;

#[CoversClass(CastAlias::class)]
#[Medium]
final class CastAliasTest extends TestCase
{
    public function testKindKeepsTheCanonicalTypeSeparateFromTheKeyword(): void
    {
        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::NationalChar, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Unsigned, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Unsigned, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Decimal, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Float, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Double, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Double, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::GeometryCollection, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Year], array_map(static fn (CastAlias $alias): \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind => $alias->kind(), CastAlias::cases()));
    }

    public function testWordsRetainsTheOptionalCastWords(): void
    {
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written, CastAlias::SignedInteger->words());
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written, CastAlias::Float8Precision->words());
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted, CastAlias::Float8->words());
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (CastAlias::cases() as $alias) {
                if (($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) && in_array($alias, [CastAlias::Float4, CastAlias::Float8, CastAlias::Float8Precision, CastAlias::GeomCollection, CastAlias::SqlTsiYear], true)) {
                    continue;
                }
                if (($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) && $alias === CastAlias::NationalCharacter) {
                    continue;
                }
                yield "$release->value/$alias->value" => [$release, 'SELECT CAST(NULL AS ' . $alias->value . ') AS chosen'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testRenderRetainsTypedKeywordsThroughPublication(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        $published = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql));
        self::assertSame($formatter->format($sql), $formatter->format($published->toString()));
    }
}
