<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AggregateAlias values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias>
 */
final class AggregateAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Generates only synonyms of the already generated function identity.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias|null>
     */
    public static function forFunction(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction $function): ErisGenerator
    {
        $cases = [null];
        foreach (\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias::cases() as $alias) {
            if ($alias->function() === $function) {
                $cases[] = $alias;
            }
        }
        return Generators::elements($cases);
    }
}
