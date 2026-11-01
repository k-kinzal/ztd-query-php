<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ViewQueries;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;
use SqlSemantics\Statement\Query;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates ViewDefinition values from the generators of its operands.
 *
 * @implements ErisGenerator<ViewDefinition>
 */
final class ViewDefinitionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ViewDefinition> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ViewDefinition from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            Generators::suchThat(static fn (Query $query): bool => (new ViewQueries())->problem($query) === null, new QueryGenerator($release, $depth - 1), 1000),
            static fn (Query $query): ErisGenerator => Generators::map(
                static fn (array $parts): ViewDefinition => new ViewDefinition(name: $parts[0], query: $query, columns: $parts[1], algorithm: $parts[2], definer: $parts[3], security: $parts[4], check: $parts[5], ifNotExists: $parts[6], inputSource: null),
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    (new QueryWidth())->of($query) === null || (new QueryWidth())->of($query) === 0 ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::vector((new QueryWidth())->of($query), new NameGenerator($release))),
                    Generators::oneOf(Generators::constant(null), new ViewAlgorithmGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new ViewSecurityGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new ViewCheckOptionGenerator($release)),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ViewDefinition $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ViewDefinition>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ViewDefinition>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
