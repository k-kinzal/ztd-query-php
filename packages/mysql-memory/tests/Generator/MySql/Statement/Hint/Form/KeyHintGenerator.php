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
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

/**
 * Generates KeyHint values from the generators of its operands.
 *
 * @implements ErisGenerator<KeyHint>
 */
final class KeyHintGenerator implements ErisGenerator
{
    /** @var ErisGenerator<KeyHint> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of KeyHint from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): KeyHint => new KeyHint(hint: $parts[0], block: $parts[1], table: new HintTable('table_' . $parts[2], $parts[1] === null ? $parts[3] : null), indexes: $parts[4]),
            Generators::tuple(
                Generators::elements(array_values(array_filter(HintName::cases(), static fn (HintName $hint): bool => $hint->form() === HintForm::Key && $hint->available($release)))),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => 'block_' . $number, Generators::choose(0, 9))),
                Generators::choose(0, 9),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => 'block_' . $number, Generators::choose(0, 9))),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::map(static fn (int $number): string => 'index_' . $number, Generators::choose(0, 9))))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (KeyHint $value) use ($release): bool {
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
     * @return GeneratedValueSingle<KeyHint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<KeyHint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
