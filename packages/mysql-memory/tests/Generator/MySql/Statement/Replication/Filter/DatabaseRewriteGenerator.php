<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Filter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DatabaseRewrite values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite>
 */
final class DatabaseRewriteGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $from Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $to Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $from, ErisGenerator $to, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite(from: $parts[0], to: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $from,
            $to,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
