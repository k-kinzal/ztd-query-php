<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Prepared;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Interpretation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation>
 */
final class InterpretationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Contract\LanguageProfile> $language Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset> $client Generated constructor operand
     */
    public function __construct(ErisGenerator $language, ErisGenerator $client)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(language: $parts[0], client: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $language,
            $client
        ));
    }
}
