<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Relation\Hint;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IndexHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint>
 */
final class IndexHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction> $action Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope|null> $scope Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Statement\Node>> $indexes Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $action, ErisGenerator $scope, ErisGenerator $indexes, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint {
            return new \SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint(action: $parts[0], scope: $parts[1], indexes: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $action,
            $scope,
            $indexes,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
