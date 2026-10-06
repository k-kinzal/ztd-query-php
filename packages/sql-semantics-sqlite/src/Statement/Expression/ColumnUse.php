<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A name used as a value: a request to resolve it as a column at its position.
 *
 * The node holds the decoded name and qualifier only. Which occurrence and
 * declaration it denotes is a fact of the operation that contains it. A name
 * written bare, in brackets or in backticks is always this request; a word
 * in double quotes and the bare words TRUE and FALSE are other requests.
 *
 * Rule: SQLITE-COLUMN-USE-001. Facts: the resolution of
 * SQLITE-COLUMN-LOOKUP-001 and the facts of SQLITE-COLUMN-FACT-001.
 * Source: https://sqlite.org/lang_expr.html#column_names. Status: Implemented.
 *
 * @visibility public
 * @example Reading a qualified column use
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT t.a FROM t');
 *     [$query->statement->columns[0]->expression->qualifier?->name->value, $query->statement->columns[0]->expression->name->value] // => ['t', 'a']
 */
final class ColumnUse implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param QualifiedName|null $qualifier The relation the use is qualified with, with its optional schema
     */
    public function __construct(public readonly Name $name, public readonly ?QualifiedName $qualifier = null)
    {
    }

    /**
     * Resolves the name in the environment and derives its facts from the outcome.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ColumnFacts())->of((new ColumnResolver())->find($environment, $this->name, $this->qualifier));
    }

    /**
     * Writes the qualifier parts and the name.
     */
    public function render(Output $out): void
    {
        if ($this->qualifier?->schema !== null) {
            $out->name($this->qualifier->schema, NameUse::Qualifier)->symbol('.');
        }
        if ($this->qualifier !== null) {
            $out->name($this->qualifier->name, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name, NameUse::Column);
    }
}
