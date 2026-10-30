<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

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
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Assignment values from the generators of its operands.
 *
 * @implements ErisGenerator<Assignment>
 */
final class AssignmentGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Assignment> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Assignment => new Assignment(column: $parts[0], value: $parts[1], inputSource: $parts[2], operator: $parts[3]),
            Generators::tuple(
                new ColumnUseGenerator($release),
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release, $depth - 1),
                    1 => new DefaultRequestGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::constant(null),
                new AssignmentOperatorGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Assignment $value) use ($release): bool {
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
     * @return GeneratedValue<Assignment>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Assignment> $element The generated counterexample
     * @return GeneratedValue<Assignment>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
