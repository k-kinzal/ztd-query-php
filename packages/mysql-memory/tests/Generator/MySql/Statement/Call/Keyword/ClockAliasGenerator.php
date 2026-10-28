<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Keyword;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ClockAlias values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias>
 */
final class ClockAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Generates only synonyms of the already generated clock operation.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias|null>
     */
    public static function forClock(\SqlSemantics\Platform\MySql\Statement\Call\Clock $clock): ErisGenerator
    {
        $cases = [null];
        foreach (\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::cases() as $alias) {
            if ($alias->clock() === $clock) {
                $cases[] = $alias;
            }
        }
        return Generators::elements($cases);
    }
}
