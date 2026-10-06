<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\WrongRelationKind;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Reports a request that SQLite carries out only for another kind of relation than the one it names.
 *
 * Rule: SQLITE-RELATION-KIND-001. CREATE VIEW declares a view; every other
 * declaration is a table. Where a name resolves to one declaration, a
 * request that needs the other kind is a diagnostic (KindRefusal), and SQLite
 * refuses it before it looks at anything else of the request. A name that is
 * undeclared, or resolves only conditionally, leaves the kind undecided and
 * is not reported. Writing to a view with INSERT, UPDATE or DELETE succeeds
 * exactly when an INSTEAD OF trigger handles it; a context does not hold
 * triggers, so such writes are not reported.
 * Source: https://sqlite.org/lang_createview.html, https://sqlite.org/lang_createtrigger.html#instead_of_triggers.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class RelationKinds
{
    /**
     * Reports a request the kind of the named relation rules out, and tells whether it did.
     */
    public function refuse(RelationFact $fact, KindRefusal $refusal, Derivation $derivation): bool
    {
        if (!$fact->table instanceof DeclaredTable || $fact->table->table->kind === $refusal->required()) {
            return false;
        }
        $derivation->report(new WrongRelationKind($fact->table->table, $refusal));

        return true;
    }
}
