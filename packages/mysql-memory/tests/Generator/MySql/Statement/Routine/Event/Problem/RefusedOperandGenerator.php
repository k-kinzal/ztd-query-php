<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Event\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand;
use Tests\Generator\MySql\Statement\Expression\Problem\NotSupportedYetGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\MisuseGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Generator\Semantics\Statement\OperandGenerator;

/**
 * Generates RefusedOperand values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedOperand>
 */
final class RefusedOperandGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedOperand> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedOperand => new RefusedOperand(name: $parts[0], problem: $parts[1], definer: $parts[2], operands: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => !($parts[1]) instanceof Misuse || ($parts[1])->rule === MisuseRule::RecursiveReferencePosition,
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new OperandWidthGenerator($release),
                        1 => new NotSupportedYetGenerator($release),
                        2 => new MisuseGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new OperandGenerator($release)))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedOperand $value) use ($release): bool {
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
     * @return GeneratedValue<RefusedOperand>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RefusedOperand> $element The generated counterexample
     * @return GeneratedValue<RefusedOperand>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
