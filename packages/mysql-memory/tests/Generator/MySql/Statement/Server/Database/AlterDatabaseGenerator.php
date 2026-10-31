<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

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
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates AlterDatabase values from the generators of its operands.
 *
 * @implements ErisGenerator<AlterDatabase>
 */
final class AlterDatabaseGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AlterDatabase> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of AlterDatabase from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AlterDatabase => new AlterDatabase(name: $parts[0], options: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], databaseWord: $parts[4]),
            Generators::suchThat(
                static fn (array $parts): bool => (static function (array $options): bool {
                    $charset = null;
                    $collation = null;
                    foreach ($options as $option) {
                        if ($option instanceof DatabaseCharset) {
                            $charset = $option->charset->name?->value;
                        } elseif ($option instanceof DatabaseCollation) {
                            $collation = $option->collation->name?->value;
                        }
                    }

                    return $charset === null || $collation === null || Collation::named($collation)?->charset->name === Charset::named($charset)?->name;
                })(($parts[1])),
                Generators::suchThat(
                    static fn (array $parts): bool => (new \SqlSemantics\Platform\MySql\Rules\Server\DatabaseOptions())->conflict($parts[1]) === null,
                    Generators::tuple(
                        Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                        Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new DatabaseOptionGenerator($release))),
                        Generators::constant(null),
                        Generators::constant(null),
                        new DatabaseWordGenerator($release)
                    )
                )
            )
        );
        $this->delegate = Generators::map(
            static function (AlterDatabase $statement) use ($release): AlterDatabase {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof AlterDatabase, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (AlterDatabase $value) use ($release): bool {
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
     * @return GeneratedValueSingle<AlterDatabase>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AlterDatabase>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
