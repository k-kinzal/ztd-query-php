<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\SemanticGraph;
use Throwable;

/**
 * Every generated statement must be represented and written using its semantic data.
 */
final class RoundTripTarget
{
    public function __construct(
        private readonly Semantics $semantics,
        private readonly Formatter $compact,
        private readonly string $grammarVersion,
    ) {
    }

    /**
     * Records rejections, serialization failures and differences as fuzz findings.
     */
    public function verify(string $sql, string $input): void
    {
        $context = "Grammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\n{$context}");
        }
        $printed = null;
        try {
            $statement = $this->semantics->analyze($sql);
            if (!(new SemanticGraph())->isSemanticOperation($statement)) {
                throw new Error('Analysis returned a syntax representation instead of an immutable semantic operation: ' . $statement::class);
            }
            $printed = $statement->toString();
            $again = $this->semantics->analyze($printed);
            $graph = new SemanticGraph();
            if (!$graph->isSemanticOperation($again) || $graph->fingerprint($statement) !== $graph->fingerprint($again)) {
                throw new Error('Reconstruction changed semantic values or declaration ownership.');
            }
            if ($this->compact->format($printed) !== $this->compact->format($again->toString())) {
                throw new Error('Semantic reconstruction did not reach a stable SQL form.');
            }
        } catch (Throwable $failure) {
            throw new Error("Semantic round trip failed\n{$context}\nPrinted: {$printed}\nError: {$failure->getMessage()}", 0, $failure);
        }
    }
}
