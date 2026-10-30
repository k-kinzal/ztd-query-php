<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Filter;

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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates ReplicationFilter values from the generators of its operands.
 *
 * @implements ErisGenerator<ReplicationFilter>
 */
final class ReplicationFilterGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ReplicationFilter> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new FilterKindGenerator($release),
            static fn (FilterKind $kind): ErisGenerator => Generators::map(
                static fn (array $values): ReplicationFilter => new ReplicationFilter(kind: $kind, values: $values, inputSource: null),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, match ($kind) {
                    FilterKind::DoDb, FilterKind::IgnoreDb => new NameGenerator($release),
                    FilterKind::DoTable, FilterKind::IgnoreTable => Generators::map(static fn (array $names): QualifiedName => new QualifiedName($names[0], $names[1]), Generators::tuple(new NameGenerator($release), new NameGenerator($release))),
                    FilterKind::WildDoTable, FilterKind::WildIgnoreTable => Generators::map(static fn (array $names): Text => new Text($names[0]->value . '.' . $names[1]->value), Generators::tuple(new NameGenerator($release), new NameGenerator($release))),
                    FilterKind::RewriteDb => new DatabaseRewriteGenerator($release),
                }))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ReplicationFilter $value) use ($release): bool {
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
     * @return GeneratedValue<ReplicationFilter>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ReplicationFilter> $element The generated counterexample
     * @return GeneratedValue<ReplicationFilter>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
