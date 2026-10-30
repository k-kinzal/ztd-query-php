<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\MySql\Statement\Variable\SystemTargetGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\VariableScopeGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates DeferredAssignment values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredAssignment>
 */
final class DeferredAssignmentGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredAssignment> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredAssignment => new DeferredAssignment(target: $parts[0], value: $parts[1], scope: $parts[0] instanceof Name ? $parts[2] : null, qualifier: $parts[0] instanceof Name ? $parts[3] : null, inputSource: $parts[4], operator: $parts[5]),
            Generators::tuple(
                Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new NameGenerator($release),
                    1 => new SystemTargetGenerator($release),
                    2 => new UserVariableGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new RowOperandGenerator($release, $depth - 1),
                    1 => new DeferredOperandGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::oneOf(Generators::constant(null), new VariableScopeGenerator($release)),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::constant(null),
                new AssignmentOperatorGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredAssignment $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredAssignment>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredAssignment> $element The generated counterexample
     * @return GeneratedValue<DeferredAssignment>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
