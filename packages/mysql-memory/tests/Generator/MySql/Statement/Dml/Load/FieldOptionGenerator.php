<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;

/**
 * Generates FieldOption values from the generators of its operands.
 *
 * @implements ErisGenerator<FieldOption>
 */
final class FieldOptionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FieldOption> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): FieldOption => new FieldOption(kind: $parts[0], text: in_array($parts[0], [FieldOptionKind::Enclosed, FieldOptionKind::OptionallyEnclosed, FieldOptionKind::Escaped], true) ? new Text(substr($parts[1]->value, 0, 1)) : $parts[1], inputSource: $parts[2]),
            Generators::tuple(
                new FieldOptionKindGenerator($release),
                new TextGenerator($release),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (FieldOption $value) use ($release): bool {
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
     * @return GeneratedValue<FieldOption>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<FieldOption> $element The generated counterexample
     * @return GeneratedValue<FieldOption>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
