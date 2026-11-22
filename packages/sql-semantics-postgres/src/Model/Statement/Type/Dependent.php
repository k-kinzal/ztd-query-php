<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Type;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A type that cannot be decided because named inputs are missing from the context.
 *
 * It always carries the missing inputs. It is never used because a rule is
 * not implemented.
 *
 * @visibility SqlSemantics
 */
final class Dependent implements TypeFact
{
    use Snapshot;

    /**
     * @var non-empty-list<MissingInput> The inputs whose absence prevents the decision
     */
    public readonly array $missing;

    /**
     * @param list<MissingInput> $missing The inputs whose absence prevents the decision; at least one
     */
    public function __construct(array $missing)
    {
        $this->missing = Check::listOf($missing, MissingInput::class, 'A dependent type names at least one missing input.', 1);
    }
}
