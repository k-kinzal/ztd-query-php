<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Index;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A resolved SQLite index declaration with ordered key expressions.
 *
 * @visibility public
 * @example Indexing the declared code field
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $definitions = $semantics->context(['CREATE TABLE items (id INTEGER, code TEXT)']);
 *     $semantics->analyze('CREATE INDEX code_index ON items (code)', $definitions)->index->value // => 'code_index'
 */
final class CreateIndex implements SemanticsIR
{
    use Snapshot;

    /**
     * @var array<int, Identifier> The exact declared names selected by the key and predicate slots
     */
    public readonly array $bindings;

    /**
     * @var non-empty-list<IndexTerm> The key terms in native order
     */
    public readonly array $terms;

    /**
     * Checks term count, declared input slots and fixed index expression rules.
     *
     * @param list<Identifier> $heading The owning table's declared column names
     * @param list<IndexTerm> $terms The checked keys in their native order
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $index,
        public readonly Identifier $table,
        array $heading,
        array $terms,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression|null $where = null,
        public readonly bool $unique = false,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($schema->value !== '', 'An index requires a nonempty schema identifier.');
        Check::input(!str_starts_with($index->key(), 'sqlite_') && !str_starts_with($table->key(), 'sqlite_'), 'An index cannot use the reserved SQLite namespace.');
        $columns = Check::listOf($heading, Identifier::class, 'An index requires a nonempty ordered declared heading.', 1);
        $keys = Check::listOf($terms, IndexTerm::class, 'An index requires a nonempty ordered key list.', 1);
        Check::input(count($columns) <= 2000 && count($keys) <= 2000, 'An index and its declared heading must fit the 2,000-column profile.');
        $seen = [];
        foreach ($columns as $column) {
            $name = $column->key();
            Check::input(!isset($seen[$name]), 'An index requires distinct declared column names.');
            $seen[$name] = true;
        }
        $operands = [];
        foreach ($keys as $key) {
            $operands[] = [$key->expression, 0, 1];
        }
        if ($where !== null) {
            $operands[] = [$where, 0, 1];
        }
        $references = (new ExpressionScope())->check([count($columns)], $operands, false);
        $resolvedNames = [];
        foreach ($references as $reference) {
            $resolvedNames[$reference->slot] = $columns[$reference->slot];
        }
        ksort($resolvedNames);
        $this->bindings = $resolvedNames;
        $this->terms = $keys;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the absolute index name, unqualified table slot and bound operands.
     */
    public function toString(): string
    {
        $expressions = new SelectSql(preserveEmptyMembership: true);
        $source = new DefinitionSource($this->bindings);
        $keys = [];
        foreach ($this->terms as $term) {
            $keys[] = $expressions->expression($term->expression, $source) . ($term->descending ? ' DESC' : ' ASC');
        }
        $where = $this->where === null ? '' : ' WHERE ' . $expressions->expression($this->where, $source);

        return 'CREATE ' . ($this->unique ? 'UNIQUE ' : '') . 'INDEX ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '')
            . $this->schema->quoted() . '.' . $this->index->quoted() . ' ON ' . $this->table->quoted()
            . ' (' . implode(', ', $keys) . ')' . $where;
    }
}
