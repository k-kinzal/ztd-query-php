<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ServerIds values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds>
 */
final class ServerIdsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral>> $ids Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $leadingComma The generated written alternative
     */
    public function __construct(ErisGenerator $ids, ?ErisGenerator $inputSource = null, ?ErisGenerator $leadingComma = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds(ids: $parts[0], inputSource: $parts[1], leadingComma: $parts[2] && $parts[0] !== []);
        }, \Tests\Generator\Operands::tuple(
            $ids,
            $inputSource ?? Generators::constant(null),
            $leadingComma ?? Generators::bool()
        ));
    }
}
