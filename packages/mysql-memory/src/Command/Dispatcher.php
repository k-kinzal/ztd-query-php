<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Command\Definition\DropTableCommand;
use MySqlMemory\Command\Definition\CreateTableCommand;
use MySqlMemory\Command\Write\MultipleChangeCommand;
use MySqlMemory\Command\Write\ChangeCommand;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Error\ErrorCode;
use ReflectionClass;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DropDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Chooses the command that executes a resolved statement.
 *
 * A statement the emulator does not execute is refused with ER_NOT_SUPPORTED_YET.
 *
 * @visibility MySqlMemory
 */
final class Dispatcher
{
    /**
     * Answers the command of a statement.
     *
     * @throws \MySqlMemory\Error\SqlError When no command executes the statement
     */
    public function command(Statement $statement): Command
    {
        return match (true) {
            $statement instanceof Query => new QueryCommand(),
            $statement instanceof InsertRows, $statement instanceof InsertSet, $statement instanceof InsertQuery => new InsertCommand(),
            $statement instanceof Update && MultipleChangeCommand::joined($statement), $statement instanceof MultipleDelete => new MultipleChangeCommand(),
            $statement instanceof Update, $statement instanceof Delete => new ChangeCommand(),
            $statement instanceof CreateTable => new CreateTableCommand(),
            $statement instanceof DropTable, $statement instanceof TruncateTable => new DropTableCommand(),
            $statement instanceof CreateDatabase, $statement instanceof DropDatabase, $statement instanceof UseDatabase => new DatabaseCommand(),
            $statement instanceof SetVariables => new SetCommand(),
            $statement instanceof Begin, $statement instanceof StartTransaction, $statement instanceof Commit, $statement instanceof Rollback => new TransactionCommand(),
            $statement instanceof ShowWarnings, $statement instanceof ShowErrors => new WarningsCommand(),
            $statement instanceof Evaluation => new DoCommand(),
            $statement instanceof ShowTables => new ShowTablesCommand(),
            default => throw ErrorCode::NotSupportedYet->error((new ReflectionClass($statement))->getShortName()),
        };
    }
}
