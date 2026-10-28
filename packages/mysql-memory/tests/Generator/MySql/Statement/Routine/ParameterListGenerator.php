<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Composition;

/**
 * Composes ParameterList values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList>
 */
final class ParameterListGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList> */
    use Composition;

    /**
     * @param ErisGenerator<list<M\Routine\Parameter>> $parameters Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $parameters, ?ErisGenerator $relationInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Routine\ParameterList {
            return new M\Routine\ParameterList(parameters: $parts[0], relationInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $parameters,
            $relationInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }

    /**
     * Composes complete SQL and external definitions with retained parameter conditions.
     *
     * @return ErisGenerator<array{M\Statement, int, string}>
     */
    public static function definitions(GrammarRelease $release): ErisGenerator
    {
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $external = !$legacy && $release !== GrammarRelease::MySql8044;
        return Generators::map(static function (array $parts) use ($release): array {
            [$form, $name] = $parts;
            $invalidName = match ($form) {
                3, 7, 9 => '',
                4 => $name->value . ' ',
                5 => str_repeat('n', 65),
                default => null,
            };
            $second = new Name(strtoupper($name->value));
            $parameters = new M\Routine\ParameterList([
                new M\Routine\Parameter($invalidName === null ? $name : new Name($invalidName), new M\Type\Integral(M\Type\Kind\IntegralKind::Int)),
                new M\Routine\Parameter($second, $form === 2 ? new M\Type\Temporal(M\Type\Kind\TemporalKind::Timestamp, '9') : new M\Type\Integral(M\Type\Kind\IntegralKind::Int), mode: $form >= 8 ? M\Routine\ParameterMode::Out : null),
                new M\Routine\Parameter(new Name('later'), new M\Type\Integral(M\Type\Kind\IntegralKind::BigInt)),
            ]);
            $routine = new QualifiedName(new Name('routine_' . $name->value));
            $definition = in_array($form, [1, 6, 7], true)
                ? new M\Routine\CreateFunction($routine, $parameters, new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Routine\Definition\FunctionImplementation(
                    $form >= 6 ? new M\Routine\ExternalBody(new M\Literal\Text('return 1;')) : new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NumberLiteral('1')),
                    $form >= 6 ? [new M\Routine\Characteristic\RoutineLanguage(new Name('JAVASCRIPT'))] : []
                ))
                : new M\Routine\CreateProcedure($routine, $parameters, $form >= 8 ? new M\Routine\ExternalBody(new M\Literal\Text('return 1;')) : new M\Routine\Program\Block(), characteristics: $form >= 8 ? [new M\Routine\Characteristic\RoutineLanguage(new Name('JAVASCRIPT'))] : []);
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $definition), $form === 5 ? 1059 : ($invalidName === null ? 1330 : 1458), $form === 5 ? "Identifier name '" . $invalidName . "' is too long" : ($invalidName === null ? 'Duplicate parameter: ' . $second->value : "Incorrect routine name '" . $invalidName . "'")];
        }, \Tests\Generator\Operands::tuple(Generators::choose(0, $legacy ? 2 : ($external ? 9 : 5)), \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $value): string => 'arg_' . $value, Generators::choose(0, 1000)), Generators::constant(null))));
    }

}
