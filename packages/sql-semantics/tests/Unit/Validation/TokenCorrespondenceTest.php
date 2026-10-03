<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Validation\TokenCorrespondence;

#[CoversClass(TokenCorrespondence::class)]
#[Medium]
final class TokenCorrespondenceTest extends TestCase
{
    public function testKeysListTheSignificantTokensInOrder(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = (new Semantics(Dialect::Sqlite))->profile();
        $tree = $platform->parser($profile)->parse("select A, 'x' as y from t;");

        $keys = (new TokenCorrespondence())->keys($tree, $platform->productions($profile), $platform->leafKeys($profile));

        self::assertSame(['SELECT:SELECT', 'name:A', 'COMMA:,', 'string:x', 'name:y', 'FROM:FROM', 'name:t'], $keys);
    }

    public function testKeysIgnoreKeywordCaseWhitespaceAndQuoteStyle(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = (new Semantics(Dialect::Sqlite))->profile();
        $correspondence = new TokenCorrespondence();

        $source = $correspondence->keys($platform->parser($profile)->parse('select  `a`  from [t]'), $platform->productions($profile), $platform->leafKeys($profile));
        $rendered = $correspondence->keys($platform->parser($profile)->parse('SELECT `a` FROM t'), $platform->productions($profile), $platform->leafKeys($profile));

        self::assertNull($correspondence->difference($source, $rendered));
    }

    public function testDifferenceNamesTheFirstTokenThatDiffers(): void
    {
        $correspondence = new TokenCorrespondence();

        self::assertSame('token 1: source has name:a, rendered SQL has name:b', $correspondence->difference(['SELECT:SELECT', 'name:a'], ['SELECT:SELECT', 'name:b']));
        self::assertSame('token 2: source has COMMA:,, rendered SQL has (end)', $correspondence->difference(['SELECT:SELECT', 'name:a', 'COMMA:,', 'name:b'], ['SELECT:SELECT', 'name:a']));
        self::assertSame('token 0: source has (end), rendered SQL has SELECT:SELECT', $correspondence->difference([], ['SELECT:SELECT']));
    }

    public function testDifferenceOfARenderedStatementAgainstItsSourceIsNull(): void
    {
        $platform = Platforms::of('sqlite');
        $semantics = new Semantics(Dialect::Sqlite);
        $profile = $semantics->profile();
        $correspondence = new TokenCorrespondence();
        $source = 'select a, count(*) from t where a is not null group by a having count(*) > 1 order by 2 desc limit 3';

        $sourceKeys = $correspondence->keys($platform->parser($profile)->parse($source), $platform->productions($profile), $platform->leafKeys($profile));
        $renderedKeys = $correspondence->keys($platform->parser($profile)->parse($semantics->analyze($source)->toString()), $platform->productions($profile), $platform->leafKeys($profile));

        self::assertNull($correspondence->difference($sourceKeys, $renderedKeys));
        self::assertNotNull($correspondence->difference($sourceKeys, $correspondence->keys($platform->parser($profile)->parse('SELECT a FROM t'), $platform->productions($profile), $platform->leafKeys($profile))));
    }
}
