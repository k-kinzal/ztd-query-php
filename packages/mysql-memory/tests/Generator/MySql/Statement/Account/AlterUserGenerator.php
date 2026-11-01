<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
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
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\Option\AccountOptionGenerator;
use Tests\Generator\MySql\Statement\Account\Option\ResourceLimitGenerator;
use Tests\Generator\MySql\Statement\Account\Option\TlsRequirementGenerator;
use Tests\Generator\MySql\Statement\Account\Option\UserCommentGenerator;
use Tests\Generator\MySql\Statement\Account\User\UserAlterationGenerator;

/**
 * Generates AlterUser values from the generators of its operands.
 *
 * @implements ErisGenerator<AlterUser>
 */
final class AlterUserGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AlterUser> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of AlterUser from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AlterUser => new AlterUser(ifExists: $parts[0], users: $parts[1], tls: $parts[2], resources: $parts[3], options: $parts[4], comment: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]),
            Generators::suchThat(
                static fn (array $parts): bool => (static function (array $users, $tls, array $resources, array $options, $comment): bool {
                    foreach ($users as $user) {
                        if ($user instanceof UserSpecification && $user->user instanceof SessionUser && !(count($users) === 1 && $tls === null && $resources === [] && $options === [] && $comment === null)) {
                            return false;
                        }
                    }

                    return true;
                })(($parts[1]), ($parts[2]), ($parts[3]), ($parts[4]), ($parts[5]))
                && ((static function (array $users): bool {
                    foreach ($users as $user) {
                        if ($user instanceof UserSpecification && !$user->altered()) {
                            return false;
                        }
                    }

                    return true;
                })(($parts[1]))),
                Generators::tuple(
                    Generators::bool(),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new UserAlterationGenerator($release))),
                    Generators::oneOf(Generators::constant(null), new TlsRequirementGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ResourceLimitGenerator($release))),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new AccountOptionGenerator($release))),
                    Generators::oneOf(Generators::constant(null), new UserCommentGenerator($release)),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::map(
            static function (AlterUser $statement) use ($release): AlterUser {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof AlterUser, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (AlterUser $value) use ($release): bool {
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
     * @return GeneratedValueSingle<AlterUser>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AlterUser>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
