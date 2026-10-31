<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NamesAssignment;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates NamesAssignment values from the generators of its operands.
 *
 * @implements ErisGenerator<NamesAssignment>
 */
final class NamesAssignmentGenerator implements ErisGenerator
{
    /** @var ErisGenerator<NamesAssignment> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of NamesAssignment from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): NamesAssignment => new NamesAssignment(value: $parts[0], operatorSource: $parts[1], inputSource: $parts[2], operator: $parts[3]),
            Generators::tuple(
                Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release, $depth - 1),
                    1 => new RowOperandGenerator($release, $depth - 1),
                    2 => new DeferredOperandGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::constant(null),
                Generators::constant(null),
                new AssignmentOperatorGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (NamesAssignment $value) use ($release): bool {
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
     * @return GeneratedValueSingle<NamesAssignment>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<NamesAssignment>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
