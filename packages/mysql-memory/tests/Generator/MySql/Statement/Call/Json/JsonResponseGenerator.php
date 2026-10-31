<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Literal;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use Tests\Generator\MySql\Statement\LiteralGenerator;

/**
 * Generates JsonResponse values from the generators of its operands.
 *
 * @implements ErisGenerator<JsonResponse>
 */
final class JsonResponseGenerator implements ErisGenerator
{
    /** @var ErisGenerator<JsonResponse> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of JsonResponse from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new JsonResponseKindGenerator($release),
            static fn (JsonResponseKind $kind): ErisGenerator => Generators::map(
                static fn (?Literal $default): JsonResponse => new JsonResponse(kind: $kind, default: $default, inputSource: null),
                $kind === JsonResponseKind::Default ? Generators::suchThat(static fn (Literal $literal): bool => !$literal instanceof NullLiteral, new LiteralGenerator($release)) : Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (JsonResponse $value) use ($release): bool {
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
     * @return GeneratedValueSingle<JsonResponse>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<JsonResponse>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
