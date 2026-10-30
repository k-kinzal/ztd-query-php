<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

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
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall;
use Tests\Generator\MySql\Statement\Call\Keyword\ArgumentSyntaxGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredKeywordCall values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredKeywordCall>
 */
final class DeferredKeywordCallGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredKeywordCall> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            Generators::elements(array_values(array_filter(KeywordFunction::cases(), static fn (KeywordFunction $function): bool => $function->arity()[1] !== 0))),
            static function (KeywordFunction $function) use ($release, $depth): ErisGenerator {
                [$minimum, $maximum] = $function->arity();
                $aliases = array_values(array_filter(KeywordAlias::cases(), static fn (KeywordAlias $alias): bool => $alias->function() === $function));

                return Generators::map(
                    static fn (array $parts): DeferredKeywordCall => new DeferredKeywordCall(function: $function, arguments: [$parts[0], ...$parts[1]], scalarInformation: null, inputSource: null, alias: $parts[2], syntax: $function === KeywordFunction::Substring ? $parts[3] : ArgumentSyntax::Commas),
                    Generators::tuple(
                        new DeferredOperandGenerator($release, $depth - 1),
                        Generators::bind(Generators::choose(max(0, $minimum - 1), max(0, ($maximum === -1 ? 3 : $maximum) - 1)), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases)),
                        new ArgumentSyntaxGenerator($release)
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredKeywordCall $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredKeywordCall>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredKeywordCall> $element The generated counterexample
     * @return GeneratedValue<DeferredKeywordCall>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
