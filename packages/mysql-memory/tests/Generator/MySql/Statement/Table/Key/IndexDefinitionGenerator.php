<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\IndexWordGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexCommentGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexEngineAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexParserGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexUsingGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexVisibilityGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\KeyBlockSizeGenerator;

/**
 * Generates IndexDefinition values from the generators of its operands.
 *
 * @implements ErisGenerator<IndexDefinition>
 */
final class IndexDefinitionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<IndexDefinition> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of IndexDefinition from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static function (array $parts): IndexDefinition {
                [$kind, $keyParts, $name, $algorithm, $options, $constraint, $keyword, $typeKeyword] = $parts;

                return new IndexDefinition(
                    kind: $kind,
                    parts: $keyParts,
                    name: $name,
                    algorithm: $kind === IndexKind::FullText || $kind === IndexKind::Spatial ? null : $algorithm,
                    options: array_values(array_filter($options, static fn ($option): bool => (!$option instanceof IndexParser || $kind === IndexKind::FullText) && (!$option instanceof IndexUsing || ($kind !== IndexKind::FullText && $kind !== IndexKind::Spatial)))),
                    constraint: $kind === IndexKind::Primary || $kind === IndexKind::Unique ? $constraint : null,
                    keyword: $kind === IndexKind::Primary ? null : $keyword,
                    inputSource: null,
                    typeKeyword: $typeKeyword,
                );
            },
            Generators::suchThat(static fn (array $parts): bool => ($bit & 3) !== 0 || !(new IndexOrdering())->legacy($parts[0], $parts[1], $parts[0] === IndexKind::FullText || $parts[0] === IndexKind::Spatial ? null : $parts[3], $parts[4]), Generators::tuple(
                new IndexKindGenerator($release),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 508, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ArrayPartGenerator($release, $depth - 1),
                    1 => new ColumnPartGenerator($release),
                    2 => new Deferred\ExpressionPartGenerator($release, $depth - 1),
                    3 => new Deferred\ExpressionPartGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::oneOf(Generators::constant(null), new ColumnNameGenerator($release)),
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
                Generators::oneOf(Generators::constant(null), new ConstraintNameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new IndexWordGenerator($release)),
                Generators::bool()
            ))
        );
        $this->delegate = Generators::suchThat(
            static function (IndexDefinition $value) use ($release): bool {
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
     * @return GeneratedValueSingle<IndexDefinition>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<IndexDefinition>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
