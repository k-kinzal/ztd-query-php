<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\User\GranteeGenerator;
use Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;

/**
 * Generates GrantProxy values from the generators of its operands.
 *
 * @implements ErisGenerator<GrantProxy>
 */
final class GrantProxyGenerator implements ErisGenerator
{
    /** @var ErisGenerator<GrantProxy> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of GrantProxy from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): GrantProxy => new GrantProxy(proxied: $parts[0], grantees: $parts[1], withGrantOption: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]),
            Generators::suchThat(static fn (array $parts): bool => ($bit & 3) !== 0 || (array_filter($parts[1], static fn ($grantee): bool => $grantee->identification !== null) === []), Generators::tuple(
                new AccountGenerator($release),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new GranteeGenerator($release),
                    1 => Generators::suchThat(static fn (UserSpecification $user): bool => $user->granted() && $user->identification?->credential !== Credential::RandomPassword, new UserSpecificationGenerator($release)),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::bool(),
                Generators::constant(null),
                Generators::constant(null)
            ))
        );
        $this->delegate = Generators::map(
            static function (GrantProxy $statement) use ($release): GrantProxy {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof GrantProxy, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (GrantProxy $value) use ($release): bool {
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
     * @return GeneratedValueSingle<GrantProxy>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<GrantProxy>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
