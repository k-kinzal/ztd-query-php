<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Keyword;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Generates CastAlias alternatives with Eris shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias>
 */
final class CastAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias> */
    use Composition;

    /**
     * Selects a keyword alternative.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::cases());
    }

    /**
     * Selects only synonyms of the already generated type role.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias|null>
     */
    public static function forKind(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind $kind, \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $words): ErisGenerator
    {
        $cases = [null];
        foreach (\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::cases() as $alias) {
            if ($alias->kind() === $kind && $alias->words() === $words) {
                $cases[] = $alias;
            }
        }
        return Generators::elements($cases);
    }
    /**
     * Selects aliases supported by the chosen grammar release.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias>
     */
    public static function forRelease(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $legacy = $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 || $release === \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $cases = $legacy ? [
            \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::Character,
            \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::SignedInteger,
            \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::SignedInt4,
            \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::UnsignedInteger,
            \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::UnsignedInt4,
            \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::Dec,
        ] : \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias::cases();
        return Generators::elements($cases);
    }

}
