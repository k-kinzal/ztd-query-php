<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Script;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\SemanticGraph;

/**
 * An ordered sequence of SQL requests, without executing them or applying their schema effects.
 * An empty sequence represents an input consisting only of statement separators.
 * @visibility public
 * @example Keeping request order independently of declaration lookup
 *     $script = new \SqlSemantics\Statement\Script\Sequence(new \SqlSemantics\Statement\Maintenance\Reindex(), new \SqlSemantics\Statement\Transaction\Rollback());
 *     $script->toString() // => 'REINDEX; ROLLBACK;'
 */
final class Sequence implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<Operation>
     */
    public readonly array $operations;

    /**
     * Keeps flat operation boundaries; this is not a database state transition model.
     */
    public function __construct(Operation ...$operations)
    {
        foreach ($operations as $operation) {
            \SqlSemantics\Statement\Validation\Check::input(!$operation instanceof self, 'A script contains operations, not nested scripts.');
            \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->isSemanticOperation($operation), 'Every request must be an immutable semantic operation.');
        }
        $this->operations = array_values($operations);
    }

    /**
     * Reconstructs an empty or ordered request sequence with explicit boundaries.
     */
    public function toString(): string
    {
        return $this->operations === [] ? ';' : implode('; ', array_map(static fn (Operation $operation): string => $operation->toString(), $this->operations)) . ';';
    }
}
