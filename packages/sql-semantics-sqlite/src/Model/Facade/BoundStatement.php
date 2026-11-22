<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Facade;

use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Facts;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Model\Validation\Publication;

/**
 * The statement and its verified bindings during dialect lowering.
 *
 * @visibility SqlSemantics
 */
final class BoundStatement
{
    use Snapshot;

    /**
     * The facts derived for this statement against its catalogue.
     */
    public readonly Facts $facts;

    /**
     * Derives facts for the checked statement structure.
     */
    public function __construct(public readonly AnalysisContext $context, public readonly Statement $statement)
    {
        $this->facts = (new Publication())->facts($context, $statement);
    }
}
