<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Validation\Equivalence;
use Throwable;

/**
 * Every generated statement must be analyzed into a semantic structure whose rendering analyzes into the same structure.
 */
final class RoundTripTarget
{
    public function __construct(private readonly Semantics $semantics, private readonly string $grammarVersion)
    {
    }

    /**
     * Records rejections, implementation gaps, invariant violations and structural differences as fuzz findings.
     */
    public function verify(string $sql, string $input): void
    {
        $context = "Grammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\n{$context}");
        }
        $rendered = null;
        try {
            $operation = $this->semantics->analyze($sql);
            $rendered = $operation->toString();
            $again = $this->semantics->analyze($rendered);
            $difference = (new Equivalence())->difference($operation->statement, $again->statement);
            if ($difference !== null) {
                throw new Error('The structure of the rendered SQL differs from the structure of the input at ' . $difference);
            }
            if ($again->toString() !== $rendered) {
                throw new Error('The rendering is not stable: ' . $again->toString());
            }
        } catch (Throwable $failure) {
            throw new Error("Semantic round trip failed\n{$context}\nRendered: {$rendered}\nError: {$failure->getMessage()}", 0, $failure);
        }
    }
}
