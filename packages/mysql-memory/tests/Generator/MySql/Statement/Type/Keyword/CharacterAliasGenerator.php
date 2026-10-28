<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Keyword;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Generates CharacterAlias alternatives with Eris shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias>
 */
final class CharacterAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias> */
    use Composition;

    /**
     * Selects a keyword alternative.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias::cases());
    }

    /**
     * Selects only synonyms of the already generated type role.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias|null>
     */
    public static function forKind(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind $kind, bool $national): ErisGenerator
    {
        $cases = [null];
        foreach (\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias::cases() as $alias) {
            if ($alias->kind() === $kind && $alias->national() === $national) {
                $cases[] = $alias;
            }
        }
        return Generators::elements($cases);
    }
}
