<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Truncate;

use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Clears one resolved MySQL table through native DDL.
 *
 * @visibility public
 * @example Publishing a persistent truncation
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE app.report (id int)']);
 *     $semantics->analyze('TRUNCATE app.report', $declarations)->toString() // => 'TRUNCATE TABLE `app`.`report`'
 */
final class TruncateTable implements SemanticsIR
{
    use Snapshot;

    /**
     * The resolved table whose contents and allocation state are cleared.
     */
    public readonly TableIdentity $target;

    /**
     * Retains a known absolute target after validating all referencing tables.
     * @param list<TableIdentity> $referrers The referencing identities under the selected native interpretation
     */
    public function __construct(
        ?TableIdentity $target,
        array $referrers,
        public readonly GrammarRelease $release = GrammarRelease::MySql847,
    ) {
        Check::input($target !== null, 'TRUNCATE TABLE requires a resolved base table.');
        $dependencies = Check::listOf($referrers, TableIdentity::class, 'TRUNCATE TABLE requires a checked list of referencing tables.');
        (new TruncationDependents())->check($target, $dependencies);
        $this->target = $target;
    }

    /**
     * Identifies the native naming and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/foreign-key-checks=1';
    }

    /**
     * Emits the operation with the exact schema and table names.
     */
    public function toString(): string
    {
        return 'TRUNCATE TABLE ' . $this->target->quoted();
    }
}
