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
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias;

#[CoversClass(IntegralAlias::class)]
#[Medium]
final class IntegralAliasTest extends TestCase
{
    public function testKindKeepsTheCanonicalTypeSeparateFromTheKeyword(): void
    {
        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int, \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::TinyInt, \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::SmallInt, \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::MediumInt, \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int, \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::BigInt, \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::MediumInt], array_map(static fn (IntegralAlias $alias): \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind => $alias->kind(), IntegralAlias::cases()));
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
            foreach (IntegralAlias::cases() as $alias) {
                $length = '';
                yield "$release->value/$alias->value/table" => [$release, 'CREATE TABLE t (v ' . $alias->value . $length . ')'];
                yield "$release->value/$alias->value/routine" => [$release, 'CREATE PROCEDURE p(IN v ' . $alias->value . $length . ') SET @v = v'];
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
