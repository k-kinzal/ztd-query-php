<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Resolved;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Locale values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale>
 */
final class LocaleGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<list<string>> $months Generated constructor operand
     * @param ErisGenerator<list<string>> $shortMonths Generated constructor operand
     * @param ErisGenerator<list<string>> $days Generated constructor operand
     * @param ErisGenerator<list<string>> $shortDays Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $months, ErisGenerator $shortMonths, ErisGenerator $days, ErisGenerator $shortDays)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale(name: $parts[0], months: $parts[1], shortMonths: $parts[2], days: $parts[3], shortDays: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $months,
            $shortMonths,
            $days,
            $shortDays
        ));
    }
    /**
     * Generates a complete locale row from the public catalog's related operands.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale>
     */
    public static function catalog(): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(Generators::elements(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale::all()), static fn (\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale $locale): self => new self(
            Generators::constant($locale->name),
            Generators::constant($locale->months),
            Generators::constant($locale->shortMonths),
            Generators::constant($locale->days),
            Generators::constant($locale->shortDays),
        ));
    }

}
