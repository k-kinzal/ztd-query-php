<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Call\Json\JsonResponseGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\MySql\Statement\Type\CastTargetGenerator;

/**
 * Generates DeferredJsonValueCall values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredJsonValueCall>
 */
final class DeferredJsonValueCallGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredJsonValueCall> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredJsonValueCall => new DeferredJsonValueCall(document: (new Precedence())->admits($parts[0], Precedence::SIMPLE_EXPR) ? $parts[0] : ($parts[0] instanceof Scalar ? new Grouped($parts[0]) : ($parts[0] instanceof RowOperand ? new GroupedRow($parts[0]) : new DeferredGrouped($parts[0]))), path: $parts[1], returning: $parts[2], onEmpty: $parts[3], onError: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]),
            Generators::tuple(
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new RowOperandGenerator($release, $depth - 1),
                    1 => new DeferredOperandGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                new StringLiteralGenerator($release),
                Generators::oneOf(Generators::constant(null), new CastTargetGenerator($release)),
                Generators::oneOf(Generators::constant(null), new JsonResponseGenerator($release)),
                Generators::oneOf(Generators::constant(null), new JsonResponseGenerator($release)),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredJsonValueCall $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredJsonValueCall>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredJsonValueCall> $element The generated counterexample
     * @return GeneratedValue<DeferredJsonValueCall>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
