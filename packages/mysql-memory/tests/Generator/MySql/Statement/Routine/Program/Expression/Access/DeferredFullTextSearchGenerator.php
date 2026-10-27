<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Access;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredFullTextSearch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch>
 */
final class DeferredFullTextSearchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $against Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode>|null $mode Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $parentheses Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $stated Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $columns, ErisGenerator $against, ?ErisGenerator $mode = null, ?ErisGenerator $parentheses = null, ?ErisGenerator $stated = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch(columns: $parts[0], against: $parts[1], mode: $parts[2], parentheses: $parts[3], stated: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $columns,
            $against,
            $mode ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode::NaturalLanguage),
            $parentheses ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written),
            $stated ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
