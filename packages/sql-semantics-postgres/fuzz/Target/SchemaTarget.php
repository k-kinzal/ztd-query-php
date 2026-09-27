<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Facade\Schema;
use Throwable;

/**
 * Every planned definition must survive analysis, writing and immutable composition.
 */
final class SchemaTarget
{
    public function __construct(private readonly Schema $semantics, private readonly Formatter $compact, private readonly string $grammarVersion)
    {
    }

    /**
     * No generated input or semantic rejection is filtered out.
     */
    public function verify(string $sql, string $input): void
    {
        try {
            $state = $this->semantics->analyze($sql);
            if (count($state->tables) !== 1) {
                throw new Error('A planned declaration must define one table.');
            }
            $before = serialize($state);
            $printed = \SqlSemantics\Statement\Writer::render($state->tables[0]->source);
            if ($this->compact->format($sql) !== $this->compact->format($printed)) {
                throw new Error('Schema state lost declaration structure.');
            }
            $again = $this->semantics->analyze($printed);
            if (serialize($again) !== $before || serialize($state) !== $before) {
                throw new Error('Schema state is not stable across reconstruction.');
            }
            if (str_contains($before, 'SqlParser\\')) {
                throw new Error('Schema state retained a parser object.');
            }
            $reset = $this->semantics->analyze('DROP TABLE IF EXISTS schema_fuzz_previous; ' . $printed . ';');
            if (serialize($reset) !== $before) {
                throw new Error('An unrelated conditional reset changed declared state.');
            }
        } catch (Throwable $error) {
            throw new Error("Schema property failed\nGrammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}\n{$error->getMessage()}", 0, $error);
        }
    }
}
