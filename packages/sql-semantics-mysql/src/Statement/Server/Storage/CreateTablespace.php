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
 * `CREATE TABLESPACE name [ADD DATAFILE 'file'] [USE LOGFILE GROUP group] [option …]`: a request to create a general tablespace.
 *
 * Mirrors PT_create_tablespace (Sql_cmd_create_tablespace) and the CREATE_TABLESPACE command of st_alter_tablespace in MySQL 5.x. Rule: MYSQL-CREATE-TABLESPACE-001. MySQL 5.x requires the data file; MySQL 8.0 lets InnoDB name it. USE LOGFILE GROUP is for NDB. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A tablespace is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Creating a tablespace
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create tablespace ts add datafile 'ts.ibd' engine = InnoDB");
 *     [$statement->toString(), $statement->datafile?->value] // => ["CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd' ENGINE = InnoDB", 'ts.ibd']
 */
final class CreateTablespace extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
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
     * @param Text|null $datafile The data file of ADD DATAFILE, when written
     * @param Name|null $logfileGroup The log file group of USE LOGFILE GROUP, when written
     * @param list<StorageOption> $options The options in written order
     * @param list<bool>|null $optionsCommas Optional commas between adjacent options
     */
    public function __construct(public readonly Name $name, public readonly ?Text $datafile = null, public readonly ?Name $logfileGroup = null, array $options = [], ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?array $optionsCommas = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->options = (new StorageOptions())->checked($options, StorageOptions::TABLESPACE);
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
        $out->keyword('CREATE', 'TABLESPACE')->name($this->name, NameUse::Identifier);
        if ($this->datafile !== null) {
            $out->keyword('ADD', 'DATAFILE')->node($this->datafile);
        }
        if ($this->logfileGroup !== null) {
            $out->keyword('USE', 'LOGFILE', 'GROUP')->name($this->logfileGroup, NameUse::Identifier);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Rendering\OptionalCommas())->render($out, $this->options, $this->optionsCommas);
    }
}
