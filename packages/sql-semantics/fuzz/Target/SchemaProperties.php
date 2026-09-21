<?php

declare(strict_types=1);

namespace Fuzz\Target;

use RuntimeException;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

/**
 * Independent, schema-aware oracles complement unrestricted grammar generation.
 * Values and destination order vary with every fuzz input; no generated SQL is filtered.
 */
final class SchemaProperties
{
    private readonly Binder $binder;

    /**
     * Builds a known catalog once for the same dialect and release as the grammar target.
     */
    public function __construct(public readonly Dialect $dialect, string $version)
    {
        $this->binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE semantic_property (id INTEGER NOT NULL, n INTEGER DEFAULT 7)'));
    }

    /**
     * Checks positional meaning and predicate diagnostics against independently known facts.
     *
     * @throws RuntimeException
     */
    public function verify(string $input): void
    {
        $first = ord($input[0] ?? "\0");
        $second = ord($input[1] ?? "\0");
        $names = $first % 2 === 0 ? ['n', 'id'] : ['id', 'n'];
        $insert = $this->binder->bind('INSERT INTO semantic_property (' . implode(',', $names) . ') VALUES (' . $first . ',' . $second . ')');
        $actual = array_map(static fn ($column): ?string => $column->binding?->column->name, $insert->insertion->columns ?? []);
        if ($actual !== $names || array_column($insert->rows[0] ?? [], 'symbol') !== [(string) $first, (string) $second]) {
            throw new RuntimeException('Schema oracle: INSERT lost positional storage meaning.');
        }
        $update = $this->binder->bind('UPDATE semantic_property SET n=' . $second . ' WHERE id=' . $first);
        if (($update->writes[0]->targets[0]->binding?->column->name ?? null) !== 'n' || ($update->writes[0]->value->symbol ?? null) !== (string) $second || ($update->where?->operands[0]->binding?->column->name ?? null) !== 'id') {
            throw new RuntimeException('Schema oracle: UPDATE lost its destination, value, or row predicate.');
        }
        $unknown = $this->binder->analyze('INSERT INTO semantic_property (missing) VALUES (' . $first . ')');
        if (!in_array('unknown-column', array_column($unknown->diagnostics, 'reason'), true)) {
            throw new RuntimeException('Schema oracle: an unknown INSERT destination was accepted without a diagnostic.');
        }
        if ($this->dialect === Dialect::PostgreSql) {
            $invalid = $this->binder->analyze('DELETE FROM semantic_property WHERE ' . $first);
            if (!in_array('non-boolean-predicate', array_column($invalid->diagnostics, 'reason'), true)) {
                throw new RuntimeException('Schema oracle: a non-boolean row predicate was accepted without a diagnostic.');
            }
        }
        $select = $this->binder->bind('SELECT id FROM semantic_property');
        $changed = $this->binder->replaceExpression($select, $select->outputs[0]->expression, 'n+' . $second);
        if (($changed->outputs[0]->expression->lineage()[0]->column->name ?? null) !== 'n' || serialize($changed) !== serialize($this->binder->bind($changed->toSql()))) {
            throw new RuntimeException('Schema oracle: an expression edit left stale semantic or source information.');
        }
    }
}
