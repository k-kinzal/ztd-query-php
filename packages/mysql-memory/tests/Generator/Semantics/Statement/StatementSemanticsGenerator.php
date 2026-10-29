<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StatementSemantics values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\StatementSemantics>
 */
final class StatementSemanticsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\StatementSemantics> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Contract\LanguageProfile> $profile Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $output Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Table>>|null $declarations Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Fact\Diagnostic>>|null $diagnostics Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Fact\Warning|\SqlSemantics\Statement\Fact\Notice>>|null $warnings Generated constructor operand
     */
    public function __construct(ErisGenerator $profile, ?ErisGenerator $output = null, ?ErisGenerator $declarations = null, ?ErisGenerator $diagnostics = null, ?ErisGenerator $warnings = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\StatementSemantics {
            return new \SqlSemantics\Statement\StatementSemantics(profile: $parts[0], output: $parts[1], declarations: $parts[2], diagnostics: $parts[3], warnings: $parts[4]);
        }, Generators::tuple(
            $profile,
            $output ?? Generators::constant(null),
            $declarations ?? Generators::constant([]),
            $diagnostics ?? Generators::constant([]),
            $warnings ?? Generators::constant([]),
        ));
    }
}
