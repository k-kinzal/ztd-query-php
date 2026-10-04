<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\DefineChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CREATE kind name [arguments] ( definition )`: defines an aggregate, operator, base or shell type, text search object or collation.
 *
 * Mirrors PostgreSQL's `DefineStmt` (`kind`, `oldstyle`, `defnames`, `args`,
 * `definition`, `if_not_exists`, `replace`). The definition is the list of
 * named attributes the command passes on as written. An aggregate is written
 * with its argument list, or in the old style without one, where every
 * attribute has a value. `CREATE TYPE name` alone defines a shell type.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, https://www.postgresql.org/docs/17/sql-createoperator.html,
 * https://www.postgresql.org/docs/17/sql-createtype.html, https://www.postgresql.org/docs/17/sql-createtsdictionary.html,
 * https://www.postgresql.org/docs/17/sql-createcollation.html.
 *
 * @visibility public
 * @example Reading an operator definition
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OPERATOR === (leftarg = int4, rightarg = int4, function = int4eq)');
 *     [$operation->statement->kind->value, count($operation->statement->definition ?? [])] // => ['OPERATOR', 3]
 */
final class Define implements Statement
{
    use Snapshot;

    /**
     * @var list<Definition>|null The attributes in written order; null for a shell type
     */
    public readonly ?array $definition;

    /**
     * @param ObjectKind $kind AGGREGATE, OPERATOR, TYPE, one of the four text search kinds, or COLLATION
     * @param DottedName|OperatorName $name The object name; an operator for OPERATOR
     * @param list<Definition>|null $definition The attributes, at least one; null for a shell type
     * @param AggregateArguments|null $arguments The argument list of an aggregate; null for an old-style aggregate and other kinds
     * @param bool $replace Whether OR REPLACE is written; aggregates only
     * @param bool $ifNotExists Whether IF NOT EXISTS is written; collations only
     */
    public function __construct(
        public readonly ObjectKind $kind,
        public readonly DottedName|OperatorName $name,
        ?array $definition,
        public readonly ?AggregateArguments $arguments = null,
        public readonly bool $replace = false,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input(in_array($kind, [ObjectKind::Aggregate, ObjectKind::Operator, ObjectKind::Type, ObjectKind::TextSearchParser, ObjectKind::TextSearchDictionary, ObjectKind::TextSearchTemplate, ObjectKind::TextSearchConfiguration, ObjectKind::Collation], true), 'CREATE with a definition defines an aggregate, an operator, a type, a text search object or a collation.');
        Check::input(($kind === ObjectKind::Operator) === ($name instanceof OperatorName) && (!$name instanceof OperatorName || !$name->explicit), 'An operator, and only an operator, is named by an operator name written without OPERATOR(...).');
        Check::input($definition !== null || $kind === ObjectKind::Type, 'Only a shell type is defined without attributes.');
        $this->definition = $definition === null ? null : Check::listOf($definition, Definition::class, 'A definition has at least one attribute.', 1);
        foreach ($this->definition ?? [] as $attribute) {
            Check::input($attribute->qualifier === null, 'A definition attribute has no namespace.');
            Check::input($kind !== ObjectKind::Aggregate || $arguments !== null || $attribute->argument !== null, 'Every attribute of an old-style aggregate has a value.');
        }
        Check::input($arguments === null || $kind === ObjectKind::Aggregate, 'Only an aggregate has an argument list.');
        Check::input(!$replace || $kind === ObjectKind::Aggregate, 'Only an aggregate is defined with OR REPLACE.');
        Check::input(!$ifNotExists || $kind === ObjectKind::Collation, 'Only a collation is defined with IF NOT EXISTS.');
    }

    /**
     * Derives the arguments and attributes and reports attributes the server requires.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        $this->arguments?->deriveClause($derivation, $environment);
        foreach ($this->definition ?? [] as $attribute) {
            $attribute->deriveClause($derivation, $environment);
        }
        (new DefineChecks())->derive($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->replace) {
            $out->keyword('OR', 'REPLACE');
        }
        (new ObjectSpelling())->kind($out, $this->kind);
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->node($this->name)->node($this->arguments);
        if ($this->definition !== null && $this->kind === ObjectKind::Aggregate && $this->arguments === null) {
            $out->symbol('(');
            foreach ($this->definition as $index => $attribute) {
                if ($index > 0) {
                    $out->symbol(',');
                }
                $out->name($attribute->name, NameUse::Identifier)->symbol('=')->node($attribute->argument);
            }
            $out->symbol(')');
        } elseif ($this->definition !== null) {
            $out->symbol('(')->list($this->definition)->symbol(')');
        }
    }
}
