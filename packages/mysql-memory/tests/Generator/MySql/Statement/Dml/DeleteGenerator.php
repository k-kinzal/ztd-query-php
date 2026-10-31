<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

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
use SqlSemantics\Platform\MySql\Rules\Dml\ValueRoles;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\MySql\Statement\Query\With\WithGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Delete values from the generators of its operands.
 *
 * @implements ErisGenerator<Delete>
 */
final class DeleteGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Delete> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Delete from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): Delete => new Delete(with: $parts[0], options: $parts[1], table: ($bit & 508) !== 0 ? $parts[2] : new WriteTarget(name: $parts[2]->name, alias: null, partitions: $parts[2]->partitions, mark: AliasMark::As, relationInformation: null, inputSource: null), where: $parts[3], orderBy: $parts[4], limit: $parts[5] instanceof RowLimit && $parts[5]->offset !== null ? new RowLimit($parts[5]->count) : $parts[5], hints: $parts[6], statementInformation: $parts[7], inputSource: $parts[8]),
            Generators::suchThat(
                static fn (array $parts): bool => (static function (array $parts): bool {
                    foreach ($parts as $part) {
                        foreach (is_array($part) ? $part : [$part] as $node) {
                            if ($node instanceof Node && (new ValueRoles())->problem($node) !== null) {
                                return false;
                            }
                        }
                    }

                    return true;
                })($parts),
                Generators::tuple(
                    $depth > 0 && ($bit & 508) !== 0 ? Generators::oneOf(Generators::constant(null), new WithGenerator($release, $depth - 1)) : Generators::constant(null),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new DeleteOptionGenerator($release))),
                    new WriteTargetGenerator($release),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::map(static fn (array $item): OrderItem => new OrderItem($item[0], $item[1]), Generators::tuple(new ScalarGenerator($release, $depth - 1), Generators::oneOf(Generators::constant(null), new DirectionGenerator($release)))))) : Generators::constant([]),
                    Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                    ($bit & 510) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new OptimizerHintGenerator($release))) : Generators::constant([]),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::map(
            static function (Delete $statement) use ($release): Delete {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof Delete, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (Delete $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Delete>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Delete>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
