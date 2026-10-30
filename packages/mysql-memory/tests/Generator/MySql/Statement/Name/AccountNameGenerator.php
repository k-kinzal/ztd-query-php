<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

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
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\NameQuote;
use Tests\Generator\MySql\Statement\Literal\Quoted\NotationGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates AccountName values from the generators of its operands.
 *
 * @implements ErisGenerator<AccountName>
 */
final class AccountNameGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AccountName> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            Generators::tuple(new NameGenerator($release), Generators::oneOf(Generators::constant(null), new NameGenerator($release))),
            static function (array $names) use ($release): ErisGenerator {
                [$user, $host] = $names;
                $quotable = static fn (?Name $name): bool => $name !== null && in_array($name->quote, [null, NameQuote::SingleQuote], true);

                return Generators::map(
                    static fn (array $parts): AccountName => new AccountName(user: $user, host: $host, inputSource: null, userQuotation: $parts[0], hostQuotation: $parts[1]),
                    Generators::tuple(
                        $quotable($user) ? Generators::oneOf(Generators::constant(null), new NotationGenerator($release)) : Generators::constant(null),
                        $quotable($host) ? Generators::oneOf(Generators::constant(null), new NotationGenerator($release)) : Generators::constant(null)
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (AccountName $value) use ($release): bool {
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
     * @return GeneratedValue<AccountName>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AccountName> $element The generated counterexample
     * @return GeneratedValue<AccountName>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
