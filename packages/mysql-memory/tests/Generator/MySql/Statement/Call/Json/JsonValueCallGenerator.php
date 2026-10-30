<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Type\CastTargetGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates JsonValueCall values from the generators of its operands.
 *
 * @implements ErisGenerator<JsonValueCall>
 */
final class JsonValueCallGenerator implements ErisGenerator
{
    /** @var ErisGenerator<JsonValueCall> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): JsonValueCall => new JsonValueCall(document: (new Precedence())->admits($parts[0], Precedence::SIMPLE_EXPR) ? $parts[0] : new Grouped($parts[0]), path: $parts[1], returning: $parts[2], onEmpty: $parts[3], onError: $parts[4], scalarInformation: null, inputSource: null, pathValue: null),
            Generators::tuple(
                new ScalarGenerator($release, $depth - 1),
                new StringLiteralGenerator($release),
                Generators::oneOf(Generators::constant(null), new CastTargetGenerator($release)),
                Generators::oneOf(Generators::constant(null), new JsonResponseGenerator($release)),
                Generators::oneOf(Generators::constant(null), new JsonResponseGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (JsonValueCall $value) use ($release): bool {
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
     * @return GeneratedValue<JsonValueCall>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<JsonValueCall> $element The generated counterexample
     * @return GeneratedValue<JsonValueCall>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
