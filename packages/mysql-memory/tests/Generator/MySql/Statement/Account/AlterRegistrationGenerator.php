<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

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
use SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration;
use SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\User\FactorGenerator;
use Tests\Generator\MySql\Statement\Account\User\RegistrationStepGenerator;
use Tests\Generator\MySql\Statement\Account\User\SessionUserGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Support\Analysis;

/**
 * Generates AlterRegistration values from the generators of its operands.
 *
 * @implements ErisGenerator<AlterRegistration>
 */
final class AlterRegistrationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AlterRegistration> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of AlterRegistration from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AlterRegistration => new AlterRegistration(ifExists: $parts[0], user: $parts[1], factor: $parts[2], step: $parts[3], challengeResponse: $parts[3] === RegistrationStep::Finish ? $parts[4] ?? new Text('response') : null, statementInformation: $parts[5], inputSource: $parts[6]),
            Generators::tuple(
                Generators::bool(),
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new AccountGenerator($release),
                    1 => new SessionUserGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                new FactorGenerator($release),
                new RegistrationStepGenerator($release),
                Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::map(
            static function (AlterRegistration $statement) use ($release): AlterRegistration {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof AlterRegistration, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (AlterRegistration $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (AlterRegistration $value) use ($release): bool {
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
     * @return GeneratedValueSingle<AlterRegistration>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AlterRegistration>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
