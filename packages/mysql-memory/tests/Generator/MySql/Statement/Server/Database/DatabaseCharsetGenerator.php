<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

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
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Name\CharsetNameGenerator;

/**
 * Generates DatabaseCharset values from the generators of its operands.
 *
 * @implements ErisGenerator<DatabaseCharset>
 */
final class DatabaseCharsetGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DatabaseCharset> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DatabaseCharset from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DatabaseCharset => new DatabaseCharset(charset: $parts[0], inputSource: $parts[1], operator: $parts[2], defaultKeyword: $parts[3], form: $parts[4]),
            Generators::tuple(
                new CharsetNameGenerator($release),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release)),
                Generators::bool(),
                Generators::elements(array_values(array_filter(CharsetForm::cases(), static fn (CharsetForm $form): bool => $form->named())))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DatabaseCharset $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DatabaseCharset>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DatabaseCharset>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
