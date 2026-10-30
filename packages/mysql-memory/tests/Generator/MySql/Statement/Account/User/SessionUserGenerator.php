<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

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
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;

/**
 * Generates SessionUser values from the generators of its operands.
 *
 * @implements ErisGenerator<SessionUser>
 */
final class SessionUserGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SessionUser> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (?KeywordAlias $alias): SessionUser => new SessionUser(inputSource: null, alias: $alias),
            Generators::oneOf(Generators::constant(null), Generators::elements(array_values(array_filter(KeywordAlias::cases(), static fn (KeywordAlias $alias): bool => $alias->function() === KeywordFunction::User))))
        );
        $this->delegate = Generators::suchThat(
            static function (SessionUser $value) use ($release): bool {
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
     * @return GeneratedValue<SessionUser>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<SessionUser> $element The generated counterexample
     * @return GeneratedValue<SessionUser>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
