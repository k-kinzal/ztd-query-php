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
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\IndexWord>|null $keyword Generated constructor operand
     */
    public function __construct(ErisGenerator $action, ErisGenerator $scope, ErisGenerator $indexes, ?ErisGenerator $inputSource = null, ?ErisGenerator $keyword = null)
    {
        $this->delegate = Generators::bind(Generators::tuple(
            $action,
            $scope,
            $indexes,
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($keyword): ErisGenerator {
            return Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Table\IndexWord $selected): \SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint => new \SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint(action: $parts[0], scope: $parts[1], indexes: $parts[2], inputSource: $parts[3], keyword: $selected), $keyword ?? new \Tests\Generator\MySql\Statement\Table\IndexWordGenerator());
        });
    }
}
