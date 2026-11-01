<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

/**
 * Generates TableHint values from the generators of its operands.
 *
 * @implements ErisGenerator<TableHint>
 */
final class TableHintGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TableHint> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of TableHint from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            Generators::elements(array_values(array_filter(HintName::cases(), static fn (HintName $hint): bool => in_array($hint->form(), [HintForm::Table, HintForm::JoinOrder, HintForm::FixedOrder], true) && $hint->available($release)))),
            static fn (HintName $hint): ErisGenerator => Generators::map(
                static fn (array $parts): TableHint => new TableHint(hint: $hint, block: $parts[0], tables: $hint->form() === HintForm::FixedOrder ? [] : array_map(static fn (array $table): HintTable => new HintTable('table_' . $table[0], $parts[0] === null ? $table[1] : null), $parts[1])),
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => 'block_' . $number, Generators::choose(0, 9))),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::tuple(Generators::choose(0, 9), Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => 'block_' . $number, Generators::choose(0, 9))))))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (TableHint $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<TableHint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<TableHint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
