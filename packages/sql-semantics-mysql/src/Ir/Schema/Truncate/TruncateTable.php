<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Truncate;

use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Clears one resolved persistent MySQL table through native DDL.
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
     * Retains the absolute identity and the fixed grammar release.
     */
    public function __construct(
        public readonly TableName $target,
        public readonly GrammarRelease $release = GrammarRelease::MySql847,
    ) {
    }

    /**
     * Identifies the native naming and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Emits the operation with the exact schema and table names.
     */
    public function toString(): string
    {
        return 'TRUNCATE TABLE ' . $this->target->quoted();
    }
}
