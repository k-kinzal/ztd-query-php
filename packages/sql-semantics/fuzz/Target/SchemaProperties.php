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
     * Builds known table definitions once for the same dialect and release as the grammar target.
     */
    public function __construct(public readonly Dialect $dialect, string $version)
    {
        $type = new \SqlSemantics\Type\TypeDescriptor($dialect, 'integer');
        $function = new \SqlSemantics\Schema\FunctionSignature('semantic_signature', [$type], $type, \SqlSemantics\Type\Nullability::NotNull, true);
        $this->binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version, functions: [$function]))->build('CREATE TABLE semantic_property (id INTEGER NOT NULL, n INTEGER DEFAULT 7)'));
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
        $function = $this->binder->bind('SELECT semantic_signature(id), semantic_signature(NULL) FROM semantic_property');
        if ($function->outputs[0]->expression->type->name !== 'integer' || $function->outputs[1]->expression->nullability !== \SqlSemantics\Type\Nullability::AlwaysNull) {
            throw new RuntimeException('Schema oracle: a registered function lost its type or NULL behavior.');
        }
        $index = $this->binder->bind('CREATE INDEX semantic_index ON semantic_property(id,n)');
        if (array_map(static fn ($key): ?string => $key->binding?->column->name, $index->indexes[0]->keys ?? []) !== ['id', 'n']) {
            throw new RuntimeException('Schema oracle: an index lost its ordered column bindings.');
        }
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
        $unknown = $this->binder->bind('INSERT INTO semantic_property (missing) VALUES (' . $first . ')', strict: false);
        if (!in_array('unknown-column', array_column($unknown->diagnostics, 'reason'), true)) {
            throw new RuntimeException('Schema oracle: an unknown INSERT destination was accepted without a diagnostic.');
        }
        if ($this->dialect === Dialect::PostgreSql) {
            $invalid = $this->binder->bind('DELETE FROM semantic_property WHERE ' . $first, strict: false);
            if (!in_array('non-boolean-predicate', array_column($invalid->diagnostics, 'reason'), true)) {
                throw new RuntimeException('Schema oracle: a non-boolean row predicate was accepted without a diagnostic.');
            }
        }
        $built = (new \SqlSemantics\StatementFactory($this->binder->schema))->select([new \SqlSemantics\Model\OutputColumn(0, 'value', \SqlSemantics\Model\Expression::literal($second, $this->dialect))]);
        if ($built->outputs[0]->name !== 'value' || $built->outputs[0]->expression->symbol !== (string) $second || SemanticFacts::read($built) !== SemanticFacts::read($this->binder->bind($built->toString()))) {
            throw new RuntimeException('Schema oracle: structure-only construction lost its value or result facts.');
        }
        $select = $this->binder->bind('SELECT id FROM semantic_property');
        $changed = $select->replaceExpression($select->outputs[0]->expression, \SqlSemantics\Model\Expression::binary('+', \SqlSemantics\Model\Expression::reference(['n'], $this->dialect), \SqlSemantics\Model\Expression::literal($second, $this->dialect)));
        if (($changed->outputs[0]->expression->lineage()[0]->column->name ?? null) !== 'n' || SemanticFacts::read($changed) !== SemanticFacts::read($this->binder->bind($changed->toString())) || $select->outputs[0]->expression->binding?->column->name !== 'id') {
            throw new RuntimeException('Schema oracle: an expression edit left stale semantic or source information.');
        }
    }
}
