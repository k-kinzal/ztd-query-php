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
            if ($engine !== null && ServerCatalog::shared()->engine($engine) === null) {
                if ($context->modes->has('NO_ENGINE_SUBSTITUTION')) {
                    throw SchemaError::UnknownStorageEngine->error($engine);
                }
                $context->warning(SchemaError::UnknownStorageEngine, $engine);
            }
        }
        foreach ((new Walker())->find($statement, CreateTable::class) as $create) {
            $this->engine($create, $session->variables, $context);
        }
    }

    /**
     * Resolves the selected engine, optionally reporting the fallback after resolve() checked its name.
     */
    public function engine(CreateTable $create, Variables $variables, ?Context $context = null): string
    {
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
    public function check(CreateTable $create, Session $session, Context $context): void
    {
        $engine = $this->engine($create, $session->variables);
        $this->attributes($create, $engine);
        foreach ($create->options as $option) {
            if ($option instanceof TextOption && in_array($option->kind, [TextOptionKind::DataDirectory, TextOptionKind::IndexDirectory], true) && !str_starts_with($option->value->bytes(), '/')) {
                if ($session->settings()->legacy()) {
                    throw SchemaError::WrongTableName->error($option->value->bytes());
                }
                throw \MySqlMemory\Error\Family\DataError::WrongValue->error('path', $option->value->bytes());
            }
            if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651 && $engine === 'InnoDB' && $option instanceof TablespaceOption && !in_array($option->tablespace->value, ['innodb_system', 'innodb_file_per_table'], true) && !isset($session->instance->registry->tablespaces[$option->tablespace->value])) {
                if ($session->settings()->legacy()) {
                    throw new SqlError(SchemaError::TablespaceUnavailable, 'InnoDB: A general tablespace named `' . $option->tablespace->value . '` cannot be found.', null, [[SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($create->name->name->value)]]);
                }
                throw SchemaError::TablespaceMissing->error($option->tablespace->value);
            }
        }
        if ($engine === 'InnoDB') {
            (new InnoDbOptions())->check($create, $session, $context);
        }
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
