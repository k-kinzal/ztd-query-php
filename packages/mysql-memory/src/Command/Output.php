<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
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

        $results = $context->variables->read('character_set_results');

        return new ResultSet($this->columns($plan, is_string($results) ? Charset::named($results) : null), $rows, $context->diagnostics->count());
    }

    /**
     * Answers the column definitions of a plan.
     *
     * @return list<ResultColumn>
     */
    public function columns(QueryPlan $plan, ?Charset $results = null): array
    {
        $columns = [];
        foreach ($plan->domains as $position => $domain) {
            $origin = $plan->origins[$position] ?? null;
            $columns[] = $this->column($plan->names[$position] ?? '', $domain, $origin, $results);
        }

        return $columns;
    }

    /**
     * Answers the definition of one column.
     *
     * A string is sent converted to the character set of the results, so its length counts the
     * bytes of its characters in that set; a set without results keeps the column's own.
     */
    public function column(string $name, Domain $domain, ?ColumnOrigin $origin, ?Charset $results = null): ResultColumn
    {
        $text = ($domain->kind === Kind::String || $domain->kind === Kind::Json) && !$domain->collation->bytes();
        $charset = $text ? ($results === null ? $domain->collation->id : $results->defaultCollation(GrammarRelease::MySql847)->id) : 63;
        $field = $domain->field === Field::Enum || $domain->field === Field::Set ? Field::String : $domain->field;
        $length = $text && $results !== null ? $this->converted($domain->length, $results->maxLength) : $domain->byteLength();

        return new ResultColumn($name, $field, $length, $domain->decimals, $domain->flags() | ($origin?->flags ?? 0), $charset, $origin?->column ?? '', $origin?->table ?? '', $origin?->originalTable ?? '', $origin?->schema ?? '');
    }

    /**
     * Answers the bytes of a number of characters in a character set, at most what a length field holds.
     */
    public function converted(int $characters, int $width): int
    {
        $bytes = $characters * $width;

        return $bytes > 4294967295 ? intdiv(4294967295, $width) * $width : $bytes;
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
