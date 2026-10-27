<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StatementSemantics values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics>
 */
final class StatementSemanticsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\StatementSemantics> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Contract\LanguageProfile> $profile Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null>|null $client Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $output Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Table>>|null $declarations Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Fact\Diagnostic>>|null $diagnostics Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Fact\Warning|\SqlSemantics\Statement\Fact\Notice>>|null $warnings Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation|null>|null $connection Generated constructor operand
     */
    public function __construct(ErisGenerator $profile, ?ErisGenerator $client = null, ?ErisGenerator $output = null, ?ErisGenerator $declarations = null, ?ErisGenerator $diagnostics = null, ?ErisGenerator $warnings = null, ?ErisGenerator $connection = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\StatementSemantics {
            [$statement, $client, $connection] = $parts;
            return new \SqlSemantics\Platform\MySql\Statement\StatementSemantics($statement->profile, $client, $statement->output, $statement->declarations, $statement->diagnostics, $statement->notices, $connection);
        }, \Tests\Generator\Operands::tuple(
            new \Tests\Generator\Semantics\Statement\StatementSemanticsGenerator($profile, $output, $declarations, $diagnostics, $warnings),
            $client ?? Generators::constant(null),
            $connection ?? Generators::constant(null)
        ));
    }
}
