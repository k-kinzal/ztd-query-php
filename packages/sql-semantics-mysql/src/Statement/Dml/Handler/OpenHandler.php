<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;

/**
 * The open handler a HANDLER ... READ reads from, named by the alias or table name of its HANDLER ... OPEN.
 *
 * Rule: MYSQL-HANDLER-TABLE-001. A handler is opened by an earlier
 * statement of the session; which table it reads is session state, so its
 * row shape is open unless the context supplies the handler's table and its declaration. The WHERE condition of the
 * read sees the handler under its name. Terminates: no child. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/handler.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the handler of a read
 *     $read = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('HANDLER h READ FIRST');
 *     $read->statement->handler->name->value // => 'h'
 */
final class OpenHandler implements Relation
{
    use Snapshot;

    /**
     * @param Name $name The name of the handler
     */
    public function __construct(public readonly Name $name)
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check(new \SqlSemantics\Statement\Identifier\QualifiedName($name));
    }

    /**
     * Resolves a supplied handler table, otherwise retains its dependence on session state.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $table = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context)->handler($this->name->value);
        if ($table !== null) {
            return (new \SqlSemantics\Platform\MySql\Rules\Query\TableShapes())->named($table, $derivation, $environment);
        }

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
