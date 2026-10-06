<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyClauses;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An EXCLUDE table constraint: no two rows satisfy all the operators of the elements together.
 *
 * Mirrors `CONSTR_EXCLUSION` with `access_method`, `exclusions`,
 * `including`, `options`, `indexspace`, `where_clause` and the attributes.
 * The keys and the predicate are derived where the columns of the table are
 * visible.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-EXCLUDE.
 *
 * @visibility public
 * @example Reading an exclusion constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (c circle, EXCLUDE USING gist (c WITH OPERATOR(pg_catalog.&&)) WHERE (c IS NOT NULL))');
 *     $create->toString() // => 'CREATE TABLE t (c circle, EXCLUDE USING gist (c WITH OPERATOR (pg_catalog.&&)) WHERE (c IS NOT NULL))'
 */
final class Exclusion implements Constraint
{
    use Snapshot;

    /**
     * @var non-empty-list<ExclusionElement> The elements
     */
    public readonly array $elements;

    /**
     * @var list<Name> The included columns
     */
    public readonly array $included;

    /**
     * @var list<Definition> The storage parameters of the index
     */
    public readonly array $options;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param list<ExclusionElement> $elements The elements; at least one
     * @param Name|null $method The index access method
     * @param list<Name> $included The included columns
     * @param list<Definition> $options The storage parameters of the index
     * @param Name|null $tablespace The tablespace of the index
     * @param Scalar|null $where The predicate of a partial constraint
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     * @param Name|null $name The constraint name
     */
    public function __construct(
        array $elements,
        public readonly ?Name $method = null,
        array $included = [],
        array $options = [],
        public readonly ?Name $tablespace = null,
        public readonly ?Scalar $where = null,
        array $attributes = [],
        public readonly ?Name $name = null,
    ) {
        $this->elements = Check::listOf($elements, ExclusionElement::class, 'An exclusion constraint has at least one element.', 1);
        $this->included = Check::listOf($included, Name::class, 'Included columns are names.');
        $this->options = Check::listOf($options, Definition::class, 'Index storage parameters are definitions.');
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Exclusion;
    }

    /**
     * Derives the elements and the predicate against the table.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->elements as $element) {
            $element->deriveClause($derivation, $environment);
        }
        (new KeyClauses())->derive($derivation, $environment, $this->included, $this->options);
        if ($this->where !== null) {
            (new Conditions())->derive($derivation, $this->where, $environment, 'WHERE');
        }
        (new Attributes())->report($derivation, $this->attributes, 'EXCLUDE', true, false, false);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $writing = new Writing();
        $writing->constraintName($out, $this->name);
        $out->keyword('EXCLUDE');
        if ($this->method !== null) {
            $out->keyword('USING')->name($this->method);
        }
        $out->symbol('(')->list($this->elements)->symbol(')');
        (new KeyClauses())->write($out, [], $this->included, $this->options, $this->tablespace);
        if ($this->where !== null) {
            $out->keyword('WHERE')->symbol('(')->node($this->where)->symbol(')');
        }
        $writing->sequence($out, $this->attributes);
    }
}
