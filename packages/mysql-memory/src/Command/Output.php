<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Context;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use MySqlMemory\Value\Real;

/**
 * Executes a query plan and writes its rows as the server sends them: each value in its text.
 *
 * @visibility MySqlMemory
 */
final class Output
{
    /**
     * Executes a plan and answers its result set.
     *
     * @throws \MySqlMemory\Error\SqlError When computing a row fails
     */
    public function result(QueryPlan $plan, Context $context): ResultSet
    {
        $iterator = (new Builder())->build($plan->root);
        $iterator->init(new Frame($context));
        $width = count($plan->domains);
        $rows = [];
        while (($row = $iterator->read()) !== null) {
            $values = [];
            for ($i = 0; $i < $width; $i++) {
                $values[] = $this->text($row[$i] ?? null, $plan->domains[$i]);
            }
            $rows[] = $values;
        }
        $context->variables->foundRows = count($rows);

        return new ResultSet($this->columns($plan), $rows, $context->diagnostics->count());
    }

    /**
     * Answers the column definitions of a plan.
     *
     * @return list<ResultColumn>
     */
    public function columns(QueryPlan $plan): array
    {
        $columns = [];
        foreach ($plan->domains as $position => $domain) {
            $origin = $plan->origins[$position] ?? null;
            $columns[] = $this->column($plan->names[$position] ?? '', $domain, $origin);
        }

        return $columns;
    }

    /**
     * Answers the definition of one column.
     */
    public function column(string $name, Domain $domain, ?ColumnOrigin $origin): ResultColumn
    {
        $charset = $domain->kind === Kind::String || $domain->kind === Kind::Json ? $domain->collation->id : 63;
        $field = $domain->field === Field::Enum || $domain->field === Field::Set ? Field::String : $domain->field;

        return new ResultColumn($name, $field, $domain->byteLength(), $domain->decimals, $domain->flags() | ($origin?->flags ?? 0), $charset, $origin?->column ?? '', $origin?->table ?? '', $origin?->originalTable ?? '', $origin?->schema ?? '');
    }

    /**
     * Writes a value of a domain in the text the server sends.
     */
    public function text(int|float|string|null $value, Domain $domain): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($domain->kind === Kind::Double && $domain->field === Field::Float && $domain->decimals >= Domain::NOT_FIXED) {
            return Real::format((float) sprintf('%.6G', (float) $value));
        }
        if ($domain->kind === Kind::Bit) {
            return (string) $value;
        }

        return Convert::toText($value, $domain);
    }
}
