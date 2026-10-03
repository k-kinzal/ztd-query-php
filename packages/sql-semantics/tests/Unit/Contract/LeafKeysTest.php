<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LeafKeys;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[CoversClass(LeafKeys::class)]
#[Medium]
final class LeafKeysTest extends TestCase
{
    public function testKeyIdentifiesALiteralByItsValueAndAKeywordByItsTerminal(): void
    {
        $profile = (new Semantics(Dialect::Sqlite))->profile();
        $platform = Platforms::of('sqlite');
        $tokens = $platform->parser($profile)->tokenize('select 1');
        $keys = $platform->leafKeys($profile);

        self::assertSame('SELECT:SELECT', $keys->key($tokens[0], 'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt orderby_opt limit_opt', 0));
        self::assertSame('number:1', $keys->key($tokens[1], 'term: INTEGER', 0));
    }

    public function testKeyIsNullAtADeclaredNoisePosition(): void
    {
        $profile = (new Semantics(Dialect::Sqlite))->profile();
        $platform = Platforms::of('sqlite');
        $tokens = $platform->parser($profile)->tokenize('SELECT 1 AS x');

        self::assertNull($platform->leafKeys($profile)->key($tokens[2], 'as: AS nm', 0));
        self::assertSame('name:x', $platform->leafKeys($profile)->key($tokens[3], 'nm: idj', 0));
    }
}
