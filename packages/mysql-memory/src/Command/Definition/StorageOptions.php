<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Variables;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;

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
     * Resolves the selected engine, optionally reporting errors and substitution warnings.
     *
     * @throws SqlError When an unknown engine is refused by the current SQL mode
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
            if ($context->modes->has('NO_ENGINE_SUBSTITUTION')) {
                throw SchemaError::UnknownStorageEngine->error($name);
            }
            $context->warning(SchemaError::UnknownStorageEngine, $name);
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
        $engine = $this->engine($create, $session->variables, $context);
        $this->attributes($create, $engine);
        foreach ($create->options as $option) {
            if ($option instanceof TextOption && in_array($option->kind, [TextOptionKind::DataDirectory, TextOptionKind::IndexDirectory], true) && !str_starts_with($option->value->bytes(), '/')) {
                if ($session->settings()->legacy()) {
                    throw SchemaError::WrongTableName->error($option->value->bytes());
                }
                throw \MySqlMemory\Error\Family\DataError::WrongValue->error('path', $option->value->bytes());
            }
            if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651 && $engine === 'InnoDB' && $option instanceof TablespaceOption && !in_array($option->tablespace->value, ['innodb_system', 'innodb_file_per_table'], true) && !isset($session->instance->registry->tablespaces[$option->tablespace->value])) {
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
    public function attributes(\SqlSemantics\Statement\Node $statement, string $engine): void
    {
        foreach ((new Walker())->find($statement, \SqlSemantics\Statement\Node::class) as $node) {
            if (($node instanceof EngineAttribute || $node instanceof IndexEngineAttribute) && !$node->secondary
                || $node instanceof TextOption && $node->kind === TextOptionKind::EngineAttribute) {
                throw SchemaError::EngineAttributeUnsupported->error($engine);
            }
        }
    }
}
