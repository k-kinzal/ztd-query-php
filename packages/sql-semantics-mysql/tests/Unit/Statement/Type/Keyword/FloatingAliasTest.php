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
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\FloatingAlias;

#[CoversClass(FloatingAlias::class)]
#[Medium]
final class FloatingAliasTest extends TestCase
{
    public function testKindKeepsTheCanonicalTypeSeparateFromTheKeyword(): void
    {
        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Float, \SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Double, \SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Double, \SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Double], array_map(static fn (FloatingAlias $alias): \SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind => $alias->kind(), FloatingAlias::cases()));
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
            foreach (FloatingAlias::cases() as $alias) {
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
