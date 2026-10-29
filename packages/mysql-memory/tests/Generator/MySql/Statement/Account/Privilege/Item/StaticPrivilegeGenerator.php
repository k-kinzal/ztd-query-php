<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Item;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StaticPrivilege values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege>
 */
final class StaticPrivilegeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>>|null $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $schemas Generated SHOW SCHEMAS spelling
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $columns = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $schemas = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(kind: $parts[0], columns: $parts[1], inputSource: $parts[2], schemas: $parts[0] === \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::ShowDatabases && $parts[3]);
        }, Generators::tuple(
            $kind,
            $columns ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null),
            $schemas ?? Generators::bool()
        ));
    }
}
