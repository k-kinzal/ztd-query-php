<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DefinitionIssue values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue>
 */
final class DefinitionIssueGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Generates the complete semantic condition consumed by input failure handling.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Partition/Problem/DefinitionIssue' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self()];
    }

}
