<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\LeafKeys;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(LeafKeys::class)]
#[Medium]
final class LeafKeysTest extends TestCase
{
    public function testKeyIdentifiesALiteralByItsValueAndAKeywordByItsTerminal(): void
    {
        $profile = (new Semantics())->profile();
        $platform = Platform::shared();
        $tokens = $platform->parser($profile)->tokenize('select 1');
        $keys = $platform->leafKeys($profile);

        self::assertSame('SELECT:SELECT', $keys->key($tokens[0], 'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt orderby_opt limit_opt', 0));
        self::assertSame('number:1', $keys->key($tokens[1], 'term: INTEGER', 0));
    }

    public function testKeyIsNullAtADeclaredNoisePosition(): void
    {
        $profile = (new Semantics())->profile();
        $platform = Platform::shared();
        $tokens = $platform->parser($profile)->tokenize('SELECT 1;');

        self::assertNull($platform->leafKeys($profile)->key($tokens[2], 'ecmd: cmdx SEMI', 1));
        self::assertSame('number:1', $platform->leafKeys($profile)->key($tokens[1], 'term: INTEGER', 0));
    }

}
