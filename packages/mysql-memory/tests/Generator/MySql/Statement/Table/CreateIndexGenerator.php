<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

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
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Alter\Modifier\AlgorithmOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\LockOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ArrayPartGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ColumnPartGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexAlgorithmGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexCommentGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexEngineAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexParserGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexUsingGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexVisibilityGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\KeyBlockSizeGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates CreateIndex values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateIndex>
 */
final class CreateIndexGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateIndex> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CreateIndex from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): CreateIndex => new CreateIndex(name: $parts[0], table: $parts[1], parts: $parts[2], kind: $parts[3], algorithm: in_array($parts[3], [IndexKind::Index, IndexKind::Unique], true) ? $parts[4] : null, options: array_values(array_filter($parts[5], static fn ($option): bool => (!$option instanceof IndexParser || $parts[3] === IndexKind::FullText) && (!$option instanceof IndexUsing || in_array($parts[3], [IndexKind::Index, IndexKind::Unique], true)))), alterOptions: $parts[6], relationInformation: $parts[7], statementInformation: $parts[8], inputSource: $parts[9], typeKeyword: $parts[10]),
            Generators::suchThat(
                static fn (array $parts): bool => count(array_filter(($parts[6]), static fn ($option): bool => $option instanceof AlgorithmOption)) <= 1 && count(array_filter(($parts[6]), static fn ($option): bool => $option instanceof LockOption)) <= 1,
                Generators::suchThat(static fn (array $parts): bool => ($bit & 3) !== 0 || !(new IndexOrdering())->legacy($parts[3], $parts[2], in_array($parts[3], [IndexKind::Index, IndexKind::Unique], true) ? $parts[4] : null, array_values(array_filter($parts[5], static fn ($option): bool => (!$option instanceof IndexParser || $parts[3] === IndexKind::FullText) && (!$option instanceof IndexUsing || in_array($parts[3], [IndexKind::Index, IndexKind::Unique], true))))), Generators::tuple(
                    new NameGenerator($release),
                    new QualifiedNameGenerator($release),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 508, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ArrayPartGenerator($release, $depth - 1),
                        1 => new ColumnPartGenerator($release),
                        2 => new Key\Deferred\ExpressionPartGenerator($release, $depth - 1),
                        3 => new Key\Deferred\ExpressionPartGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    Generators::elements(array_values(array_filter(IndexKind::cases(), static fn (IndexKind $kind): bool => $kind !== IndexKind::Primary))),
                    Generators::oneOf(Generators::constant(null), new IndexAlgorithmGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 5), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new IndexCommentGenerator($release),
                        1 => new IndexEngineAttributeGenerator($release),
                        2 => new IndexParserGenerator($release),
                        3 => new IndexUsingGenerator($release),
                        4 => new IndexVisibilityGenerator($release),
                        5 => new KeyBlockSizeGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new AlgorithmOptionGenerator($release),
                        1 => new LockOptionGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::bool()
                ))
            )
        );
        $this->delegate = Generators::map(
            static function (CreateIndex $statement) use ($release): CreateIndex {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof CreateIndex, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (CreateIndex $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CreateIndex>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateIndex>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
