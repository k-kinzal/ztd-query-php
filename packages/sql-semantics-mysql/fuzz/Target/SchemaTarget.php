<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Validation\Equivalence;
use Throwable;

/**
 * Every generated table definition must declare one table, round-trip structurally, declare the same table from its rendering, and stay the same structure under any context.
 */
final class SchemaTarget
{
    public function __construct(private readonly Semantics $semantics, private readonly string $grammarVersion)
    {
    }

    /**
     * No generated input or semantic rejection is filtered out.
     */
    public function verify(string $sql, string $input): void
    {
        try {
            $operation = $this->semantics->analyze($sql, []);
            if (count($operation->declarations()) !== 1) {
                throw new Error('A table definition must declare exactly one table; ' . count($operation->declarations()) . ' declared.');
            }
            $rendered = $operation->toString();
            $again = $this->semantics->analyze($rendered, []);
            $difference = (new Equivalence())->difference($operation->statement, $again->statement);
            if ($difference !== null) {
                throw new Error('The structure of the rendered definition differs from the structure of the input at ' . $difference);
            }
            if ($again->toString() !== $rendered) {
                throw new Error('The rendering is not stable: ' . $again->toString());
            }
            if (count($again->declarations()) !== 1) {
                throw new Error('The rendered definition must declare exactly one table; ' . count($again->declarations()) . ' declared.');
            }
            $difference = (new Equivalence())->difference($operation->declarations()[0], $again->declarations()[0]);
            if ($difference !== null) {
                throw new Error('The table declared by the rendered definition differs from the table declared by the input at ' . $difference);
            }
            $dependent = $this->semantics->analyze($rendered, [$operation]);
            $difference = (new Equivalence())->difference($operation->statement, $dependent->statement);
            if ($difference !== null) {
                throw new Error('The declaration context changed the structure of the definition at ' . $difference);
            }
            if (count($dependent->declarations()) !== 1) {
                throw new Error('A table definition analyzed with its own declaration in the context must still declare one table.');
            }
            $difference = (new Equivalence())->difference($operation->declarations()[0], $dependent->declarations()[0]);
            if ($difference !== null) {
                throw new Error('The declaration context changed the declared table at ' . $difference);
            }
        } catch (Throwable $error) {
            throw new Error("Schema property failed\nGrammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}\n{$error->getMessage()}", 0, $error);
        }
    }
}
