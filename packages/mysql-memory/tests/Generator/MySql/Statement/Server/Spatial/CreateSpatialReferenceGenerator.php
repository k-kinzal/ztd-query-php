<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Spatial;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes CreateSpatialReference values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference>
 */
final class CreateSpatialReferenceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference> */
    use Composition;

    /**
     * Generates complete CreateSpatialReference values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When the release has no spatial reference definitions.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($release->database() !== 'mysql' || in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            throw new InvalidArgumentException('Spatial reference definitions require MySQL 8.0 or later.');
        }
        if ($operands === null) {
            $forms = [self::requests()];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference) {
                throw new LogicException('Publishing CreateSpatialReference must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Composes arbitrary attribute order and repetitions with identifier limits.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference>
     */
    public static function requests(): ErisGenerator
    {
        $numbers = new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::oneOf(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100000)), Generators::elements('2147483648', '4294967295', '4294967296')));
        $attribute = Generators::bind(new SpatialAttributeKindGenerator(), static fn (\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind $kind): ErisGenerator => new SpatialAttributeGenerator(
            Generators::constant($kind),
            new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::elements('', 'name', 'text', '😀')),
            $kind === \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Organization ? $numbers : Generators::constant(null)
        ));
        return Generators::bind(Generators::bool(), static fn (bool $replace): ErisGenerator => self::operands(
            Generators::constant($replace),
            $replace ? Generators::constant(false) : Generators::bool(),
            $numbers,
            Generators::bind(Generators::choose(0, 6), static fn (int $count): ErisGenerator => Generators::vector($count, $attribute))
        ));
    }

    /**
     * @param ErisGenerator<bool> $orReplace Generated constructor operand
     * @param ErisGenerator<bool> $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $srid Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute>> $attributes Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference>
     */
    public static function operands(ErisGenerator $orReplace, ErisGenerator $ifNotExists, ErisGenerator $srid, ErisGenerator $attributes, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference(orReplace: $parts[0], ifNotExists: $parts[1], srid: $parts[2], attributes: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple($orReplace, $ifNotExists, $srid, $attributes, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference>>
     */
    public static function forms(): iterable
    {
        yield 'CreateSpatialReference/26' => CreateSpatialReferenceGenerator::operands(
            orReplace: Generators::constant(true),
            ifNotExists: Generators::constant(false),
            srid: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('1'),
                hexadecimal: Generators::constant(false)
            ),
            attributes: Generators::constant([])
        );
        yield 'CreateSpatialReference/144' => CreateSpatialReferenceGenerator::operands(
            orReplace: Generators::constant(true),
            ifNotExists: Generators::constant(false),
            srid: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('1'),
                hexadecimal: Generators::constant(false)
            ),
            attributes: Generators::tuple(
                new SpatialAttributeGenerator(
                    kind: SpatialAttributeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name]),
                    value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    ),
                    identifier: Generators::constant(null)
                )
            )
        );
    }

}
