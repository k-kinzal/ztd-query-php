<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The open handler a HANDLER ... READ reads from, named by the alias or table name of its HANDLER ... OPEN.
 *
 * Rule: MYSQL-HANDLER-TABLE-001. A handler is opened by an earlier
 * statement of the session; which table it reads is session state, so its
 * row shape is open and depends on that state. The WHERE condition of the
 * read sees the handler under its name. Terminates: no child. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/handler.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class OpenHandler implements Relation
{
    use Snapshot;

    /**
     * @param Name $name The name of the handler
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives the open row shape of the handler.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return new RelationFact(new RowShape([], [new SessionState('handler ' . $this->name->value)]));
    }

    /**
     * Writes the handler name.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Relation);
    }
}
