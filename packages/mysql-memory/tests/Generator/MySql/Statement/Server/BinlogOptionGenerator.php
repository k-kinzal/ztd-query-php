<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes BinlogOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption>
 */
final class BinlogOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Generates an absent modifier or either suppression keyword.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption|null>
     */
    public static function optional(): ErisGenerator
    {
        return Generators::oneOf(...[Generators::constant(null), new self()]);
    }

}
