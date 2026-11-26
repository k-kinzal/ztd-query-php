<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OpenStar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * `SHOW parameter`, `SHOW ALL` or a keyword form: the current value of a configuration parameter.
 *
 * Rule: PG-SHOW-001. Mirrors PostgreSQL's `VariableShowStmt`. Facts: SHOW
 * ALL returns the text columns `name`, `setting` and `description`, of which
 * a setting or description may be absent; a keyword form returns one text
 * column named after the parameter it stands for (`TimeZone`,
 * `transaction_isolation`, `session_authorization`). A named parameter
 * returns one text column named as the server registered the parameter,
 * which may differ in letter case from the name written and is unknown for
 * the parameter of an extension, so the row depends on the server's
 * parameter registry. Termination: no recursion.
 * Source: https://www.postgresql.org/docs/17/sql-show.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Show implements Statement
{
    use Snapshot;

    /**
     * @param ParameterName|SpecialParameter $parameter The parameter, or the keywords that name one or all
     */
    public function __construct(public readonly ParameterName|SpecialParameter $parameter)
    {
    }

    /**
     * Records the row SHOW returns.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $text = new Known(Builtin::Text);
        $projection = match ($this->parameter) {
            SpecialParameter::All => [
                new Field(0, new OutputSlot(new Name('name'), $text, Nullability::NotNull)),
                new Field(1, new OutputSlot(new Name('setting'), $text, Nullability::Nullable)),
                new Field(2, new OutputSlot(new Name('description'), $text, Nullability::Nullable)),
            ],
            SpecialParameter::TimeZone => [new Field(0, new OutputSlot(new Name('TimeZone'), $text, Nullability::NotNull))],
            SpecialParameter::TransactionIsolationLevel => [new Field(0, new OutputSlot(new Name('transaction_isolation'), $text, Nullability::NotNull))],
            SpecialParameter::SessionAuthorization => [new Field(0, new OutputSlot(new Name('session_authorization'), $text, Nullability::NotNull))],
            default => [new OpenStar([new SessionState('the registered name of the configuration parameter ' . $this->parameter->text())])],
        };
        $derivation->output(new QueryFact($projection, $derivation->context->columnNames));
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW');
        if ($this->parameter instanceof SpecialParameter) {
            $out->keyword(...explode(' ', $this->parameter->value));

            return;
        }
        $out->node($this->parameter);
    }
}
