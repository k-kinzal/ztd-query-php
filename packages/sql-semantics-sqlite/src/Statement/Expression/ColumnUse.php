<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;

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
 * @visibility SqlSemantics
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
