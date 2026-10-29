<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Method;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ColumnsMethod values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod>
 */
final class ColumnsMethodGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $columns, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod(kind: $parts[0], columns: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $kind,
            $columns,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
