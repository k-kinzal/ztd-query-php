<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem\Operand;

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
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand;
use Tests\Generator\MySql\Statement\Query\SelectGenerator;
use Tests\Generator\MySql\Statement\Query\Set\SetOperatorGenerator;

/**
 * Generates RefusedIntoSetOperand values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedIntoSetOperand>
 */
final class RefusedIntoSetOperandGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedIntoSetOperand> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedIntoSetOperand => new RefusedIntoSetOperand(operand: $parts[0], operator: $parts[1]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[0])->into !== null,
                Generators::tuple(
                    new SelectGenerator($release),
                    new SetOperatorGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedIntoSetOperand $value) use ($release): bool {
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
     * @return GeneratedValue<RefusedIntoSetOperand>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RefusedIntoSetOperand> $element The generated counterexample
     * @return GeneratedValue<RefusedIntoSetOperand>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
