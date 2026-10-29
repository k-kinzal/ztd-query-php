<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Tuple values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Tuple>
 */
final class TupleGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Tuple> */
    use Composition;

    /**
     * @param ErisGenerator<int> $width Generated constructor operand
     */
    public function __construct(ErisGenerator $width)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Tuple {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Tuple(width: $parts[0]);
        }, Generators::tuple(
            $width
        ));
    }
    /**
     * Generates equally wide, locally typed rows and compares their actual values.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function comparisons(): ErisGenerator
    {
        $rows = Generators::bind(new self(Generators::choose(2, 6)), static function (\SqlSemantics\Platform\MySql\Statement\Expression\Tuple $tuple): RowGenerator {
            $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 100)),
                new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer())), Generators::constant(\SqlSemantics\Statement\Type\Nullability::NotNull)),
            );
            return new RowGenerator(Generators::vector($tuple->width, $numbers), scalarInformation: new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(Generators::constant($tuple)), Generators::constant(\SqlSemantics\Statement\Type\Nullability::NotNull)));
        });
        $comparisons = Generators::bind($rows, static function (\SqlSemantics\Platform\MySql\Statement\Expression\Row $row): ComparisonGenerator {
            $copies = [];
            foreach ($row->elements as $element) {
                \SqlSemantics\Diagnostic\Check::invariant($element instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral, 'The generated tuple contains number literals.');
                $copies[] = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral($element->text, $element->scalarInformation());
            }
            $right = new \SqlSemantics\Platform\MySql\Statement\Expression\Row($copies, scalarInformation: $row->scalarInformation());
            return new ComparisonGenerator(
                Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal),
                Generators::constant($row),
                Generators::constant($right),
                new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer())), Generators::constant(\SqlSemantics\Statement\Type\Nullability::NotNull)),
            );
        });
        return \Tests\Generator\MySql\Statement\QueryRootGenerator::namedScalar($comparisons);
    }

}
