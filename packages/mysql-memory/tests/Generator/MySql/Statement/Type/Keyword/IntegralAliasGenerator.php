<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Keyword;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Generates IntegralAlias alternatives with Eris shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias>
 */
final class IntegralAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias> */
    use Composition;

    /**
     * Selects a keyword alternative.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias::cases());
    }

    /**
     * Selects only synonyms of the already generated type role.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias|null>
     */
    public static function forKind(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind $kind): ErisGenerator
    {
        $cases = [null];
        foreach (\SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias::cases() as $alias) {
            if ($alias->kind() === $kind) {
                $cases[] = $alias;
            }
        }
        return Generators::elements($cases);
    }
}
