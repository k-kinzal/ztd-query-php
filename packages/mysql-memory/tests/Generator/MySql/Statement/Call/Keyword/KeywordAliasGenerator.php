<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Keyword;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes KeywordAlias values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias>
 */
final class KeywordAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Generates only synonyms of the already generated function identity.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias|null>
     */
    public static function forFunction(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction $function): ErisGenerator
    {
        $cases = [null];
        foreach (\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias::cases() as $alias) {
            if ($alias->function() === $function) {
                $cases[] = $alias;
            }
        }
        return Generators::elements($cases);
    }
}
