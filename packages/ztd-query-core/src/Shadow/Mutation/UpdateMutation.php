<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies UPDATE result rows to the shadow store.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class UpdateMutation implements DataMutation
{
    /**
     * Target table to update.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Primary key columns used to match rows.
     *
     * @var list<string>
     */
    private array $primaryKeys;

    /**
     * Table definition for constraint validation.
     *
     * @var TableDefinition|null
     */
    private ?TableDefinition $tableDefinition;

    /**
     * Original SQL statement for exception messages.
     *
     * @var string
     */
    private string $sql;

    /**
     * Whether constraint validation is enabled.
     *
     * @var bool
     */
    private bool $validateConstraints;

    private RowConstraints $constraints;

    /**
     * @param string $tableName Target table.
     * @param list<string> $primaryKeys Primary key columns.
     * @param TableDefinition|null $tableDefinition Table definition for constraint validation.
     * @param string $sql Original SQL statement for exception messages.
     * @param bool $validateConstraints Whether to validate constraints.
     */
    public function __construct(
        string $tableName,
        array $primaryKeys,
        ?TableDefinition $tableDefinition = null,
        string $sql = '',
        bool $validateConstraints = false
    ) {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
        $this->tableDefinition = $tableDefinition;
        $this->sql = $sql;
        $this->validateConstraints = $validateConstraints;
        $this->constraints = new RowConstraints($this->tableDefinition, $this->tableName, $this->sql);
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $identity = new MutationRowIdentity();
        $updates = [];
        foreach ($rows as $row) {
            $updates[] = $identity->extract($row, $this->primaryKeys);
        }
        $rows = array_column($updates, 'row');

        if ($this->validateConstraints && $this->tableDefinition !== null) {
            $existingRows = $store->get($this->tableName);

            foreach ($rows as $row) {
                $this->constraints->assertNoNullWhereNoneIsAllowed($row);
                $this->constraints->assertNoDuplicateUniqueKey($row, $existingRows, $this->primaryKeys);
            }
        }

        $store->updateIdentified($this->tableName, $updates, $this->primaryKeys);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }




}
