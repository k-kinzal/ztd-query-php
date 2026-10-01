<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\Schema\DeclarationProvider;
use SqlSemantics\Statement\SemanticGraph;
use Throwable;

/**
 * Every planned declaration must resolve, declare one readable table, write back, and stay stable.
 */
final class SchemaTarget
{
    public function __construct(private readonly Semantics $semantics, private readonly Formatter $compact, private readonly string $grammarVersion)
    {
    }

    /**
     * No generated input or semantic rejection is filtered out.
     */
    public function verify(string $sql, string $input): void
    {
        try {
            $statement = $this->semantics->analyze($sql, []);
            $graph = new SemanticGraph();
            if (!$statement instanceof DeclarationProvider || !$graph->isSemanticOperation($statement) || count($statement->declaredTables()) !== 1) {
                throw new Error('A planned declaration must describe one table using immutable semantic values.');
            }
            $before = serialize($statement);
            $printed = $statement->toString();
            $again = $this->semantics->analyze($printed, []);
            if ($graph->fingerprint($statement) !== $graph->fingerprint($again)) {
                throw new Error('The declaration changed across reconstruction.');
            }
            if ($this->compact->format($printed) !== $this->compact->format($again->toString())) {
                throw new Error('Declaration reconstruction did not reach a stable SQL form.');
            }
            $reset = $this->semantics->analyze('DROP TABLE IF EXISTS schema_fuzz_previous', []);
            $after = $this->semantics->analyze($printed, [$reset]);
            if ($graph->fingerprint($after) !== $graph->fingerprint($statement)) {
                throw new Error('An unrelated conditional drop changed the declaration.');
            }
            $dependent = $this->semantics->analyze($printed, [$statement, $reset]);
            if ($graph->fingerprint($dependent) !== $graph->fingerprint($statement) || serialize($statement) !== $before) {
                throw new Error('Declaration context changed the statement itself.');
            }
        } catch (Throwable $error) {
            throw new Error("Schema property failed\nGrammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}\n{$error->getMessage()}", 0, $error);
        }
    }
}
