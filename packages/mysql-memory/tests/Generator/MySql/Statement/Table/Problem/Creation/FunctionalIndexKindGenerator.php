<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Creation;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind;
use Tests\Generator\MySql\Statement\Table\Key\ArrayPartGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ColumnPartGenerator;

/**
 * Generates FunctionalIndexKind values from the generators of its operands.
 *
 * @implements ErisGenerator<FunctionalIndexKind>
 */
final class FunctionalIndexKindGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FunctionalIndexKind> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of FunctionalIndexKind from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): FunctionalIndexKind => new FunctionalIndexKind(kind: $parts[0], owner: $parts[1]),
            Generators::suchThat(
                static fn (array $parts): bool => !($parts[1]) instanceof ColumnPart,
                Generators::tuple(
                    Generators::elements([IndexKind::Primary, IndexKind::FullText, IndexKind::Spatial]),
                    Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 508, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ArrayPartGenerator($release),
                        1 => new ColumnPartGenerator($release),
                        2 => new \Tests\Generator\MySql\Statement\Table\Key\Deferred\ExpressionPartGenerator($release),
                        3 => new \Tests\Generator\MySql\Statement\Table\Key\Deferred\ExpressionPartGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    })
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (FunctionalIndexKind $value) use ($release): bool {
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
     * @return GeneratedValueSingle<FunctionalIndexKind>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<FunctionalIndexKind>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
