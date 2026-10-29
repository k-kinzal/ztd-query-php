<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LoadInput values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput>
 */
final class LoadInputGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $local Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource> $source Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $file Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $count Generated constructor operand
     * @param ErisGenerator<bool>|null $primaryKeyOrder Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $countWord Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $from The generated written alternative
     */
    public function __construct(ErisGenerator $local, ErisGenerator $source, ErisGenerator $file, ?ErisGenerator $count = null, ?ErisGenerator $primaryKeyOrder = null, ?ErisGenerator $countWord = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $from = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput(local: $parts[0], source: $parts[1], file: $parts[2], count: $parts[3], primaryKeyOrder: $parts[4], countWord: $parts[5], inputSource: $parts[6], from: $parts[7]);
        }, Generators::tuple(
            $local,
            $source,
            $file,
            $count ?? Generators::constant(null),
            $primaryKeyOrder ?? Generators::constant(false),
            $countWord ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $from ?? Generators::bool()
        ));
    }
}
