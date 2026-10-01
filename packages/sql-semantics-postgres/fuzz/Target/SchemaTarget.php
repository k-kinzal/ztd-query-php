<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Writer;
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
            $resolution = $statement->resolution ?? throw new Error('A statement analyzed with dependencies must be resolved.');
            if (count($resolution->declarations) !== 1 || count($resolution->references) !== 1 || $resolution->references[0]->kind !== ReferenceKind::Declaration) {
                throw new Error('A planned declaration must declare one table and name nothing else.');
            }
            $before = serialize($resolution->declarations);
            $printed = Writer::render($resolution->declarations[0]->source);
            if ($this->compact->format($sql) !== $this->compact->format($printed)) {
                throw new Error('The declaration lost its structure.');
            }
            $again = $this->semantics->analyze($printed, [])->resolution;
            if ($again === null || serialize($again->declarations) !== $before || serialize($resolution->declarations) !== $before) {
                throw new Error('The declaration is not stable across reconstruction.');
            }
            if (str_contains($before, 'SqlParser\\')) {
                throw new Error('The declaration retained a parser object.');
            }
            $reset = $this->semantics->analyze('DROP TABLE IF EXISTS schema_fuzz_previous', []);
            $after = $this->semantics->analyze($printed, [$reset])->resolution;
            if ($after === null || serialize($after->declarations) !== $before) {
                throw new Error('An unrelated conditional drop changed the declaration.');
            }
            $dependent = $this->semantics->analyze($printed, [$statement, $reset]);
            if ($dependent->resolution?->references[0]->kind !== ReferenceKind::Declaration && !$dependent->resolution?->references[0]->conditional) {
                throw new Error('A repeated declaration must be rejected or read as conditional.');
            }
        } catch (\SqlSemantics\Core\SemanticException $error) {
            if ($error->reason !== 'duplicate-table') {
                throw new Error("Schema property failed\nGrammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}\n{$error->getMessage()}", 0, $error);
            }
        } catch (Throwable $error) {
            throw new Error("Schema property failed\nGrammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}\n{$error->getMessage()}", 0, $error);
        }
    }
}
