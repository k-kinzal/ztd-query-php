<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

/**
 * Composes StringLiteral values through Eris generation and shrinking.
 *
 * @implements ErisGenerator<StringLiteral>
 */
final class StringLiteralGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<StringLiteral>
     */
    private ErisGenerator $delegate;

    /**
     * @param ErisGenerator<string>|null $strings The decoded literal values
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $locations Optional independent location metadata
     */
    public function __construct(?\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation $collation = null, ?ErisGenerator $strings = null, ?ErisGenerator $locations = null)
    {
        $strings ??= Generators::map(static fn (array $characters): string => implode('', $characters), Generators::seq(Generators::elements('0', '1', 'a', "'", '\\\\', 'é', '列')));
        $this->delegate = Generators::map(static function (array $data) use ($collation): StringLiteral {
            if (!is_string($data['value']) || ($data['location'] !== null && !$data['location'] instanceof \SqlSemantics\Statement\Source\Span)) {
                throw new LogicException('Literal generators must produce decoded text and optional source metadata.');
            }
            return new StringLiteral([$data['value']], collation: $collation, location: $data['location']);
        }, Generators::associative(['value' => $strings, 'location' => $locations ?? Generators::constant(null)]));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<StringLiteral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<StringLiteral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<array<array-key, string|int|bool|object|null>> $segments The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $introducer The generated semantic operand
     * @param ErisGenerator<bool> $national The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation|null> $collation The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $location Generated input range
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated local meaning
     * @return ErisGenerator<StringLiteral>
     */
    public static function fromOperands(ErisGenerator $segments, ErisGenerator $introducer, ErisGenerator $national, ErisGenerator $collation, ?ErisGenerator $location = null, ?ErisGenerator $scalarInformation = null): ErisGenerator
    {
        return Generators::map(static fn (array $parts): StringLiteral => new StringLiteral(segments: $parts[0], introducer: $parts[1], national: $parts[2], collation: $parts[3], location: $parts[4], scalarInformation: $parts[5]), Generators::tuple($segments, $introducer, $national, $collation, $location ?? Generators::constant(null), $scalarInformation ?? Generators::constant(null)));
    }

}
