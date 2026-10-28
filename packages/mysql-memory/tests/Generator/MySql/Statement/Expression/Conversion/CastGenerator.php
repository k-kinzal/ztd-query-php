<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Conversion;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Composition;

/**
 * Composes Cast values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<M\Expression\Conversion\Cast>
 */
final class CastGenerator implements ErisGenerator
{
    /** @use Composition<M\Expression\Conversion\Cast> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $operand Generated constructor operand
     * @param ErisGenerator<M\Type\CastTarget> $target Generated constructor operand
     * @param ErisGenerator<bool>|null $array Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<M\Expression\Conversion\CastForm>|null $form The written cast form
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $target, ?ErisGenerator $array = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $form = null)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Expression\Conversion\Cast {
            return new M\Expression\Conversion\Cast(operand: $parts[0], target: $parts[1], array: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], form: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $target,
            $array ?? Generators::constant(false),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $form ?? Generators::constant(M\Expression\Conversion\CastForm::Cast)
        ));
    }

    /**
     * Composes complete ordinary and unexecuted stored requests with target conditions.
     *
     * @return ErisGenerator<array{M\Statement, M\Statement, int, int|null}>
     */
    public static function requests(GrammarRelease $release): ErisGenerator
    {
        $modern = !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        return Generators::map(static function (array $parts) use ($release): array {
            [$family, $value, $deferred, $form, $earlier] = $parts;
            $operand = $deferred ? new M\Expression\Row([new M\Literal\NumberLiteral((string) $value), new M\Literal\NullLiteral()]) : new M\Literal\NullLiteral();
            $target = match ($family) {
                1 => new M\Type\CastTarget(M\Type\Kind\CastKind::Decimal, (string) (66 + $value), '0'),
                2 => new M\Type\CastTarget(M\Type\Kind\CastKind::Decimal, '65', (string) (31 + $value)),
                3 => new M\Type\CastTarget(M\Type\Kind\CastKind::Decimal, '2', (string) (3 + $value)),
                4 => new M\Type\CastTarget(M\Type\Kind\CastKind::Char, '4294967296'),
                6 => new M\Type\CastTarget(M\Type\Kind\CastKind::Json),
                9 => new M\Type\CastTarget(M\Type\Kind\CastKind::Float, (string) (54 + $value)),
                default => new M\Type\CastTarget(M\Type\Kind\CastKind::Time, (string) (7 + $value)),
            };
            $form = $family === 5 ? M\Expression\Conversion\CastForm::AtLocal : ($family === 6 ? M\Expression\Conversion\CastForm::Cast : $form);
            $expression = match ($family) {
                7 => $operand instanceof \SqlSemantics\Statement\Scalar
                    ? new M\Expression\Conversion\AtTimeZone($operand, M\Expression\Conversion\TimeZone::ZeroOffset, precision: (string) (7 + $value))
                    : new M\Routine\Program\Expression\DeferredTimeZone($operand, new M\Literal\Text('+00:00'), precision: (string) (7 + $value)),
                8 => $operand instanceof \SqlSemantics\Statement\Scalar
                    ? new M\Call\Json\JsonValueCall($operand, new M\Literal\StringLiteral(['$']), $target)
                    : new M\Routine\Program\Expression\Call\Json\DeferredJsonValueCall($operand, new M\Literal\StringLiteral(['$']), $target),
                default => $operand instanceof \SqlSemantics\Statement\Scalar
                    ? new M\Expression\Conversion\Cast($operand, $target, $family === 6, form: $form)
                    : new M\Routine\Program\Expression\Conversion\DeferredCast($operand, $target, array: $family === 6, form: $form),
            };
            $request = new M\Dml\Evaluation([
                $expression instanceof \SqlSemantics\Statement\Scalar ? new M\Query\SelectExpression($expression) : new M\Routine\Program\DeferredProjection($expression),
                new M\Query\SelectExpression(new M\Literal\NumberLiteral('42')),
            ]);
            $definition = new M\Routine\CreateProcedure(new QualifiedName(new Name('converted_' . $value)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([
                new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), $earlier ? [new M\Dml\Evaluation([new M\Query\SelectExpression(new M\Variable\SystemVariable(new Name('ztd_missing_' . $value)))]), $request] : [$request]),
            ]));
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            $publication = new \SqlSemantics\Platform\MySql\Construction\Publication();
            return [$publication->publish($context, $request), $publication->publish($context, $definition), match ($family) {
                2 => 1425, 3 => 1427, 4 => 1439, 5, 6 => 1235, default => 1426
            }, $earlier ? 1193 : ($family === 6 ? 1221 : null)];
        }, \Tests\Generator\Operands::tuple(Generators::choose(0, $modern ? 9 : 4), Generators::choose(0, 19), Generators::bool(), CastFormGenerator::selecting([M\Expression\Conversion\CastForm::Cast, M\Expression\Conversion\CastForm::Convert]), Generators::bool()));
    }

}
