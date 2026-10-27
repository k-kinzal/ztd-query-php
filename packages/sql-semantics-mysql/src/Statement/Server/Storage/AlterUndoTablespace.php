<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Server\StorageOptions;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * `ALTER UNDO TABLESPACE name SET {ACTIVE | INACTIVE} [option …]`: a request to activate or deactivate an undo tablespace (MySQL 8.0.14 and later).
 *
 * Mirrors PT_alter_undo_tablespace (Sql_cmd_alter_undo_tablespace). Rule: MYSQL-ALTER-UNDO-TABLESPACE-001. An inactive undo tablespace can be dropped. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A tablespace is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-tablespace.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Deactivating an undo tablespace
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("alter undo tablespace u set inactive");
 *     [$statement->toString(), $statement->active] // => ["ALTER UNDO TABLESPACE u SET INACTIVE", false]
 */
final class AlterUndoTablespace extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param bool $active Whether ACTIVE (true) or INACTIVE (false) is written
     * @param list<StorageOption> $options The options in written order
     */
    public function __construct(public readonly Name $name, public readonly bool $active, array $options = [], ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->options = (new StorageOptions())->checked($options, StorageOptions::UNDO_TABLESPACE);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Reports the options the server rejects while it parses the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new StorageOptions())->derive($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'UNDO', 'TABLESPACE')->name($this->name, NameUse::Identifier)->keyword('SET', $this->active ? 'ACTIVE' : 'INACTIVE');
        (new StorageOptions())->render($out, $this->options);
    }
}
