<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Server\StorageOptions;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * `CREATE UNDO TABLESPACE name ADD DATAFILE 'file' [option …]`: a request to create an undo tablespace (MySQL 8.0.14 and later).
 *
 * Mirrors PT_create_undo_tablespace (Sql_cmd_create_undo_tablespace). Rule: MYSQL-CREATE-UNDO-TABLESPACE-001. The data file name ends in .ibu. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A tablespace is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Creating an undo tablespace
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create undo tablespace u add datafile 'u.ibu'");
 *     [$statement->toString(), $statement->datafile->value] // => ["CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'", 'u.ibu']
 */
final class CreateUndoTablespace extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<bool> The retained comma at each boundary between options
     */
    public readonly array $optionsCommas;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param Text $datafile The data file
     * @param list<StorageOption> $options The options in written order
     * @param list<bool>|null $optionsCommas Optional commas between adjacent options
     */
    public function __construct(public readonly Name $name, public readonly Text $datafile, array $options = [], ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?array $optionsCommas = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        \SqlSemantics\Diagnostic\Check::input($datafile->radix === null, 'A tablespace file name is a quoted string.');
        $this->options = (new StorageOptions())->checked($options, StorageOptions::UNDO_TABLESPACE);
        $this->optionsCommas = (new \SqlSemantics\Platform\MySql\Rules\Rendering\OptionalCommas())->checked($optionsCommas, count($this->options), false);
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
        $out->keyword('CREATE', 'UNDO', 'TABLESPACE')->name($this->name, NameUse::Identifier)->keyword('ADD', 'DATAFILE')->node($this->datafile);
        (new \SqlSemantics\Platform\MySql\Rules\Rendering\OptionalCommas())->render($out, $this->options, $this->optionsCommas);
    }
}
