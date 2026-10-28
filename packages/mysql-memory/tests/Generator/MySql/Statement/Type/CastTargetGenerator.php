<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Composition;

/**
 * Composes CastTarget values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CastTarget>
 */
final class CastTargetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\CastTarget> */
    use Composition;

    /**
     * @param ErisGenerator<M\Type\Kind\CastKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $length Generated constructor operand
     * @param ErisGenerator<string|null>|null $scale Generated constructor operand
     * @param ErisGenerator<M\Type\CharsetAttribute|null>|null $charset Generated constructor operand
     * @param ErisGenerator<M\Expression\OptionalWords>|null $words Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<M\Type\Keyword\CastAlias|null>|null $alias Generated type keyword
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $length = null, ?ErisGenerator $scale = null, ?ErisGenerator $charset = null, ?ErisGenerator $words = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple($kind, $words ?? Generators::constant(M\Expression\OptionalWords::Omitted)), static function (array $selected) use ($length, $scale, $charset, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): M\Type\CastTarget {
                return new M\Type\CastTarget(kind: $parts[0], length: $parts[1], scale: $parts[2], charset: $parts[3], words: $parts[4], inputSource: $parts[5], alias: $parts[6]);
            }, \Tests\Generator\Operands::tuple(
                Generators::constant($selected[0]),
                $length ?? Generators::constant(null),
                $scale ?? Generators::constant(null),
                $charset ?? Generators::constant(null),
                Generators::constant($selected[1]),
                $inputSource ?? Generators::constant(null),
                $alias ?? Keyword\CastAliasGenerator::forKind($selected[0], $selected[1])
            ));
        });
    }
    /**
     * Generates paired casts with one canonical target and one written synonym.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, string|null}>
     */
    public static function queries(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(Keyword\CastAliasGenerator::forRelease($release), Generators::choose(0, 100)), static function (array $parts) use ($release): ErisGenerator {
            [$alias, $number] = $parts;
            $number = $alias->kind() === M\Type\Kind\CastKind::Year ? 2000 + $number : $number;
            $targets = new self(Generators::constant($alias->kind()), words: Generators::constant($alias->words()), alias: Generators::constant($alias));
            $queries = Generators::map(static function (M\Type\CastTarget $target) use ($number): M\QueryRoot {
                $operand = $target->kind === M\Type\Kind\CastKind::GeometryCollection ? new M\Literal\NullLiteral() : new M\Literal\NumberLiteral((string) $number);
                return new M\QueryRoot(new M\Query\Select([], [
                    new M\Query\SelectExpression(new M\Expression\Conversion\Cast($operand, $target), new Name('selected')),
                    new M\Query\SelectExpression(new M\Expression\Conversion\Cast($target->kind === M\Type\Kind\CastKind::GeometryCollection ? new M\Literal\NullLiteral() : new M\Literal\NumberLiteral((string) $number), new M\Type\CastTarget($target->kind)), new Name('canonical')),
                ]));
            }, $targets);
            return \Tests\Generator\Operands::tuple(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $queries), Generators::constant($alias->kind() === M\Type\Kind\CastKind::GeometryCollection ? null : (string) $number));
        });
    }

}
