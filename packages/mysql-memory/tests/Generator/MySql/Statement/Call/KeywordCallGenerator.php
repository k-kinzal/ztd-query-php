<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call;

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
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use Tests\Generator\MySql\Statement\Call\Keyword\ArgumentSyntaxGenerator;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates KeywordCall values from the generators of its operands.
 *
 * @implements ErisGenerator<KeywordCall>
 */
final class KeywordCallGenerator implements ErisGenerator
{
    /** @var ErisGenerator<KeywordCall> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of KeywordCall from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            new KeywordFunctionGenerator($release),
            static function (KeywordFunction $function) use ($release, $depth): ErisGenerator {
                [$minimum, $maximum] = $function->arity();
                $aliases = array_values(array_filter(KeywordAlias::cases(), static fn (KeywordAlias $alias): bool => $alias->function() === $function));

                return Generators::map(
                    static fn (array $parts): KeywordCall => new KeywordCall(function: $function, arguments: $parts[0], parentheses: $parts[1], scalarInformation: null, inputSource: null, alias: $parts[2], syntax: $parts[3]),
                    Generators::tuple(
                        Generators::bind(Generators::choose($minimum, $maximum === -1 ? 3 : $maximum), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))),
                        $function === KeywordFunction::CurrentUser ? new OptionalWordsGenerator($release) : Generators::constant(OptionalWords::Written),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases)),
                        $function === KeywordFunction::Substring ? new ArgumentSyntaxGenerator($release) : Generators::constant(ArgumentSyntax::Commas)
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (KeywordCall $value) use ($release): bool {
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
     * @return GeneratedValueSingle<KeywordCall>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<KeywordCall>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
