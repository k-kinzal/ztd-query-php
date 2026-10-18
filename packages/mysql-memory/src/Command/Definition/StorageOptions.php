<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Variables;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Statement;

/**
 * Resolves storage-engine selection and checks the options accepted by the selected engine.
 *
 * Unknown engines are refused with NO_ENGINE_SUBSTITUTION, otherwise the default engine is
 * substituted with both warnings. Engine attributes require engine support after their JSON
 * has been checked. Directory paths must be absolute. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility MySqlMemory
 */
final class StorageOptions
{
    /**
     * Resolves every table and partition engine before opening tables, retaining option order.
     *
     * Unknown names warn or fail as each option is read. CREATE TABLE then warns if its final
     * table engine requires substitution. A partition's unknown engine has no second warning.
     * A missing current database precedes engine lookup. Verified through SQL on MySQL 8.4.7.
     *
     * @throws SqlError When an engine is unknown and substitution is disabled
     */
    public function resolve(Statement $statement, Session $session, Context $context): void
    {
        if ($statement instanceof CreateTable && array_key_exists(spl_object_id($statement), $session->variables->preparedEngines)) {
            return;
        }
        $name = match (true) {
            $statement instanceof CreateTable => $statement->name,
            $statement instanceof AlterTable => $statement->table,
            default => null,
        };
        if ($name !== null && $name->schema === null && $session->variables->database === '') {
            throw QueryError::NoDatabase->error();
        }
        foreach ((new Walker())->find($statement, Node::class) as $node) {
            $engine = match (true) {
                $node instanceof EngineOption => $node->engine->value,
                $node instanceof PartitionOption && $node->kind === PartitionOptionKind::Engine => $node->name?->value,
                default => null,
            };
            if ($engine !== null) {
                $this->named($engine, $context);
            }
        }
        foreach ((new Walker())->find($statement, CreateTable::class) as $create) {
            $this->engine($create, $session->variables, $context);
        }
        if ($statement instanceof CreateTable) {
            $this->directories($statement, $session);
        }
    }

    /**
     * Resolves a completed CREATE header before a refused tail, without fabricating a statement.
     *
     * @throws SqlError When the database is unspecified or an engine is unknown
     */
    public function header(CreateTable|\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $header, Session $session, Context $context): void
    {
        if ($header->name->schema === null && $session->variables->database === '') {
            throw QueryError::NoDatabase->error();
        }
        foreach ($header->options as $option) {
            if ($option instanceof EngineOption) {
                $this->named($option->engine->value, $context);
            }
        }
    }

    /**
     * Resolves the table options before a partition contextualization failure.
     *
     * The notice identifies its stored instruction; options of sibling writes
     * are not read. Engine substitution does not happen after a refused operand.
     *
     * @throws SqlError When an earlier engine name is unavailable
     */
    public function beforePartition(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure $notice, \SqlSemantics\Statement\Operation $operation, Session $session): void
    {
        if (!$notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression) {
            return;
        }
        $statement = $operation->facts->warningInstruction($notice) ?? $operation->statement;
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        if ($statement instanceof CreateTable) {
            $this->header($statement, $session, $context);
        } elseif ($statement instanceof AlterTable) {
            if ($statement->table->schema === null && $session->variables->database === '') {
                throw QueryError::NoDatabase->error();
            }
            foreach ($statement->commands as $command) {
                if ($command instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions) {
                    foreach ($command->options as $option) {
                        if ($option instanceof EngineOption) {
                            $this->named($option->engine->value, $context);
                        }
                    }
                }
            }
        }
    }

    /**
     * Resolves one engine name, preserving option order and substitution mode.
     *
     * @throws SqlError When engine substitution is disabled
     */
    public function named(string $engine, Context $context): void
    {
        if (ServerCatalog::shared()->engine($engine) === null) {
            if ($context->modes->has('NO_ENGINE_SUBSTITUTION')) {
                throw SchemaError::UnknownStorageEngine->error($engine);
            }
            $context->warning(SchemaError::UnknownStorageEngine, $engine);
        }
    }

    /**
     * Resolves the selected engine, optionally reporting the fallback after resolve() checked its name.
     */
    public function engine(CreateTable|\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Variables $variables, ?Context $context = null): string
    {
        if (isset($variables->preparedEngines[spl_object_id($create)])) {
            return $variables->preparedEngines[spl_object_id($create)];
        }
        $default = (string) $variables->read($create->temporaryWords > 0 ? 'default_tmp_storage_engine' : 'default_storage_engine');
        $name = $default;
        foreach ($create->options as $option) {
            if ($option instanceof EngineOption) {
                $name = $option->engine->value;
            }
        }
        $engine = ServerCatalog::shared()->engine($name);
        if ($engine === null && $context !== null) {
            $context->warning(SchemaError::UsingOtherEngine, $default, $create->name->name->value);
        }

        return $engine ?? $default;
    }

    /**
     * Checks the engine-specific options before the table is stored.
     *
     * @throws SqlError When an option is invalid or unsupported
     */
    public function check(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Session $session, Context $context): void
    {
        $this->directories($create, $session);
        $engine = $this->engine($create, $session->variables);
        (new Database\TableEncryption())->check($create, $engine, $session, $context);
        $this->attributes($create, $engine);
        foreach ($create->options as $option) {
            if ($option instanceof TablespaceOption) {
                $this->tablespace($option, $create, $engine, $session);
            }
        }
        if ($engine === 'InnoDB') {
            (new InnoDbOptions())->check($create, $session, $context);
        }
    }

    /**
     * Rejects relative storage directories before opening the destination schema.
     *
     * Database selection and option-name resolution precede this check. Empty
     * columns, absent schemas and IF NOT EXISTS follow it on 5.6 through 9.1.
     * Observed through SQL on 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
     */
    public function directories(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Session $session): void
    {
        foreach ($create->options as $option) {
            if ($option instanceof TextOption && in_array($option->kind, [TextOptionKind::DataDirectory, TextOptionKind::IndexDirectory], true) && !str_starts_with($option->value->bytes(), '/')) {
                if ($session->settings()->legacy()) {
                    throw SchemaError::WrongTableName->error($option->value->bytes());
                }
                throw \MySqlMemory\Error\Family\DataError::WrongValue->error('path', $option->value->bytes());
            }
        }
    }

    /**
     * Resolves a retained tablespace name against the instance catalog.
     *
     * @throws SqlError When the selected engine requires an absent tablespace
     */
    public function tablespace(TablespaceOption $option, CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, string $engine, Session $session): void
    {
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 || $engine !== 'InnoDB' || in_array($option->tablespace->value, ['innodb_system', 'innodb_file_per_table'], true) || isset($session->instance->registry->tablespaces[$option->tablespace->value])) {
            return;
        }
        if ($session->settings()->legacy()) {
            throw new SqlError(SchemaError::TablespaceUnavailable, 'InnoDB: A general tablespace named `' . $option->tablespace->value . '` cannot be found.', null, [[SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($create->name->name->value)]]);
        }
        throw SchemaError::TablespaceMissing->error($option->tablespace->value);
    }

    /**
     * Refuses primary engine attributes on engines without that capability; secondary attributes are retained.
     *
     * @throws SqlError When a primary engine attribute is present
     */
    public function attributes(Node $statement, string $engine): void
    {
        foreach ((new Walker())->find($statement, Node::class) as $node) {
            if (($node instanceof EngineAttribute || $node instanceof IndexEngineAttribute) && !$node->secondary
                || $node instanceof TextOption && $node->kind === TextOptionKind::EngineAttribute) {
                throw SchemaError::EngineAttributeUnsupported->error($engine);
            }
        }
    }
}
