<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PartitionEntryRefused values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused>
 */
final class PartitionEntryRefusedGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused> */
    use Composition;

    /**
     * Constructs the operand with no additional inputs.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (bool $unused): \SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused => new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused(), Generators::constant(false));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Alter\\Problem\\PartitionEntryRefused' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new PartitionEntryRefusedGenerator()];
    }

}
