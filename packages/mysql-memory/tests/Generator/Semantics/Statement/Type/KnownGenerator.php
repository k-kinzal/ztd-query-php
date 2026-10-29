<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Known values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Type\Known>
 */
final class KnownGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Type\Known> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Type\TypeDescriptor> $descriptor Generated constructor operand
     */
    public function __construct(ErisGenerator $descriptor)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Type\Known {
            return new \SqlSemantics\Statement\Type\Known(descriptor: $parts[0]);
        }, Generators::tuple(
            $descriptor
        ));
    }
}
