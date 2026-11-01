<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Window;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates WindowFunction values from the generators of its operands.
 *
 * @implements ErisGenerator<WindowFunction>
 */
final class WindowFunctionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<WindowFunction> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of WindowFunction from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            new WindowFunctionKindGenerator($release),
            static function (WindowFunctionKind $kind) use ($release, $depth): ErisGenerator {
                [$minimum, $maximum] = $kind->arity();

                return Generators::map(
                    static function (array $parts) use ($kind): WindowFunction {
                        [$arguments, $count, $over, $nulls, $edge] = $parts;
                        if ($kind->counted() !== null) {
                            $arguments[$kind->counted()] = $count;
                        }
                        if ($kind === WindowFunctionKind::NthValue) {
                            $arguments[1] = $count;
                        }
                        if ($kind === WindowFunctionKind::NthValue && !(new Precedence())->admits($arguments[1], Precedence::SIMPLE_EXPR)) {
                            $arguments[1] = new Grouped($arguments[1]);
                        }

                        return new WindowFunction(kind: $kind, arguments: array_values($arguments), over: $over, nulls: $kind->treatsNulls() ? $nulls : null, edge: $kind === WindowFunctionKind::NthValue ? $edge : null, scalarInformation: null, inputSource: null);
                    },
                    Generators::tuple(
                        Generators::bind(Generators::choose($minimum, $maximum), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))),
                        Generators::map(static fn (int $number): NumberLiteral => new NumberLiteral((string) $number), Generators::choose(1, 9)),
                        Generators::bind(Generators::bool(), static fn (bool $named): ErisGenerator => $named ? new NameGenerator($release) : Generators::suchThat(static fn (WindowSpecification $window): bool => (new Constraints())->problem($window) === null, new WindowSpecificationGenerator($release, $depth - 1))),
                        Generators::suchThat(static fn (?NullTreatment $nulls): bool => (new Arguments())->limitation($nulls, null) === null, Generators::oneOf(Generators::constant(null), new NullTreatmentGenerator($release))),
                        Generators::suchThat(static fn (?CountingEdge $edge): bool => (new Arguments())->limitation(null, $edge) === null, Generators::oneOf(Generators::constant(null), new CountingEdgeGenerator($release)))
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (WindowFunction $value) use ($release): bool {
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
     * @return GeneratedValueSingle<WindowFunction>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<WindowFunction>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
