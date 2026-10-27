<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Script values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Script>
 */
final class ScriptGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Script> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Statement>> $statements Generated constructor operand
     */
    public function __construct(ErisGenerator $statements)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Script {
            return new \SqlSemantics\Statement\Script(statements: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $statements
        ));
    }
    /**
     * Generates a declaration, its write and its read as one dependent script.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\Script>
     */
    public static function declaredTable(): ErisGenerator
    {
        return new self(\Tests\Generator\MySql\Statement\QueryRootGenerator::declaredTableSequence());
    }

}
