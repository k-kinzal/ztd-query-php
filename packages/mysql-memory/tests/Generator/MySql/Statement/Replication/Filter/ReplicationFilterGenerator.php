<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Filter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ReplicationFilter values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter>
 */
final class ReplicationFilterGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Statement\Identifier\QualifiedName|\SqlSemantics\Platform\MySql\Statement\Literal\Text|\SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite>> $values Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $values, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter(kind: $parts[0], values: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $kind,
            $values,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
