<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MaintainedTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>
 */
final class MaintainedTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $dot Generated legacy name spelling
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $dot = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable(name: $parts[0], inputSource: $parts[1], relationInformation: $parts[2], dot: $parts[3]);
        }, Generators::tuple(
            $name,
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null),
            $dot ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted)
        ));
    }
}
