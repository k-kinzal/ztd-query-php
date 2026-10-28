<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Keyword;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DateArithmeticAlias values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias>
 */
final class DateArithmeticAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Generates only synonyms of the already generated arithmetic operation.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias|null>
     */
    public static function forOperation(bool $subtract): ErisGenerator
    {
        $cases = [null];
        foreach (\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias::cases() as $alias) {
            if ($alias->subtracts() === $subtract) {
                $cases[] = $alias;
            }
        }
        return Generators::elements($cases);
    }
}
