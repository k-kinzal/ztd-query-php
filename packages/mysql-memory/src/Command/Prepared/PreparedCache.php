<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use LogicException;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Statement\Binding;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Invalidates a protocol preparation when a referenced declaration changes.
 *
 * Ordinary executions consume the active structure. Metadata invalidation
 * selects a typed alternative retained at preparation, under current lexical
 * and client settings in the original database. No SQL text enters refresh.
 * Inactive alternatives do not contribute dependencies to the active cache.
 *
 * @visibility MySqlMemory
 */
final class PreparedCache
{
    /**
     * @param list<array{string, string, Table|\MySqlMemory\Dictionary\View|null, bool}> $tables Schema, name, stored identity and permanent-only lookup
     */
    public function __construct(public PreparedStatement|PendingTable $definition, public array $tables, public readonly ?Definition $input = null)
    {
    }

    /**
     * Retains a definition and the declarations its named relations resolved to.
     *
     * @throws LogicException When the statement has no preparation implementation
     */
    public static function capture(\SqlSemantics\Statement\AnalyzedStatement|PendingTable $operation, Session $session, ?Definition $input = null): self
    {
        if ($operation instanceof PendingTable) {
            return new self($operation, [], $input);
        }

        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\RenameUser || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateRole || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles) {
            return new self(PreparedStatement::capture($operation, $session), [], $input);
        }
        if (!$operation instanceof MySqlStatement) {
            throw new LogicException('No prepared cache capture for ' . $operation::class);
        }
        $tables = [];
        foreach ((new Walker())->find($operation, NamedRelation::class) as $relation) {
            if (!$relation->hasBinding()) {
                continue;
            }
            $resolution = $relation->relationInformation()->table;
            if ($resolution instanceof DeclaredTable) {
                $tables[] = $resolution;
            }
        }
        return new self(PreparedStatement::capture($operation, $session), Dependencies::capture($tables, $session), $input);
    }

    /**
     * Tests declaration identities, ignoring data writes and unrelated table changes.
     */
    public function changed(Session $session): bool
    {
        $dictionary = $session->instance->dictionary;
        foreach ($this->tables as [$schema, $name, $table, $permanent]) {
            $namespace = $permanent ? null : $session->temporaries;
            if ($table instanceof Table) {
                if (!in_array($table, $dictionary->declarations($namespace), true)) {
                    return true;
                }
                continue;
            }
            $stored = $dictionary->table($schema, $name, $namespace);
            $current = $stored?->definition->declaration ?? ($dictionary->schema($schema)->views[$name] ?? null);
            if ($current !== $table) {
                return true;
            }
        }
        return false;
    }

    /**
     * Selects and binds a retained alternative, replacing the cache only on success.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The current bindings
     * @throws \MySqlMemory\Error\SqlError When the new interpretation fails
     * @throws LogicException When metadata invalidation has no typed preparation definition
     */
    public function refresh(Session $session, ParameterBindings $bindings, array $parameters): void
    {
        if (!$this->definition instanceof PreparedStatement || !$this->changed($session)) {
            return;
        }
        if ($this->input === null) {
            throw new LogicException('Metadata invalidation requires a complete typed preparation definition.');
        }
        $database = $session->variables->database;
        $domains = $session->preparation->domains;
        $session->variables->database = $this->input->database->value ?? '';
        $session->preparation->domains = [];
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_reprepare');
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_prepare');
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_execute');
        try {
            $alternative = $this->input->select(new Interpretation($session->semantics()->profile(), Charset::known((string) $session->variables->read('character_set_client'))));
            if ($alternative->statement instanceof SyntaxRefusal) {
                throw \MySqlMemory\Error\Family\StatementError::ParseError->error($alternative->statement->near, $alternative->statement->line);
            }
            $operation = (new Binding())->statement($session, $alternative->statement, $parameters, $alternative->interpretation->language, sources: $alternative->sources);
            (new \MySqlMemory\Hint\Hints())->prepare($operation, $session);
            $replacement = self::capture($operation, $session, $this->input);
            $types = ParameterBindings::capture($operation)->types;
            $this->definition = $replacement->definition;
            $this->tables = $replacement->tables;
            $bindings->types = $types;
        } finally {
            $session->variables->database = $database;
            $session->preparation->domains = $domains;
        }
    }
}
