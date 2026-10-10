<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Real;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

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
     * The rows found are what FOUND_ROWS() then answers: the rows sent, or for SQL_CALC_FOUND_ROWS
     * the rows the outermost LIMIT reads.
     *
     * @param bool $calculate Whether the query asks for SQL_CALC_FOUND_ROWS
     *
     * @throws \MySqlMemory\Error\SqlError When computing a row fails
     */
    public function result(QueryPlan $plan, Context $context, bool $calculate = false): ResultSet
    {
        $limit = $calculate && $plan->root instanceof Limit ? $plan->root : null;
        $iterator = (new Builder())->build($limit === null ? $plan->root : $limit->input);
        $context->row = 1;
        $iterator->init(new Frame($context));
        $width = count($plan->domains);
        $results = $context->variables->read('character_set_results');
        $charset = is_string($results) ? Charset::named($results) : null;
        $rows = [];
        $found = 0;
        while (($row = $iterator->read()) !== null) {
            $found++;
            $context->row = $found + 1;
            if ($limit !== null && ($found <= $limit->offset || ($limit->count !== null && $found > $limit->offset + $limit->count))) {
                continue;
            }
            $values = [];
            for ($i = 0; $i < $width; $i++) {
                $values[] = $this->sent($this->text($row[$i] ?? null, $plan->domains[$i], (($plan->origins[$i]->flags ?? 0) & ColumnFlag::ZeroFill->value) !== 0), $plan->domains[$i], $charset);
            }
            $rows[] = $values;
        }
        $context->variables->foundRows = $found;

        return new ResultSet($this->columns($plan, $charset), $rows, $context->diagnostics->count());
    }

    /**
     * Converts the text of a string value into the character set of the results; NULL results send it as it is held.
     */
    public function sent(?string $text, Domain $domain, ?Charset $results): ?string
    {
        if ($text === null || $results === null || ($domain->kind !== Kind::String && $domain->kind !== Kind::Json)) {
            return $text;
        }

        return Encoding::convert($text, $domain->kind === Kind::Json ? Charset::known('utf8mb4') : $domain->collation->charset, $results);
    }

    /**
     * Answers the column definitions of a plan, their names converted into the character set of the results.
     *
     * @return list<ResultColumn>
     */
    public function columns(QueryPlan $plan, ?Charset $results = null): array
    {
        $columns = [];
        foreach ($plan->domains as $position => $domain) {
            $origin = $plan->origins[$position] ?? null;
            $name = $plan->names[$position] ?? '';
            $columns[] = $this->column($results === null || Encoding::utf8($results) ? $name : Encoding::convert($name, Charset::known('utf8mb4'), $results), $domain, $origin, $results);
        }

        return $columns;
    }

    /**
     * Answers the definition of one column.
     *
     * A string is sent converted to the character set of the results, so its length counts the
     * bytes of its characters in that set; a set without results keeps the column's own. A
     * temporal value in a character set, as a rollup item is, is sent as such a string. A
     * ZEROFILL column carries no BINARY flag. A column of a system table carries the flags the
     * server reports for it, and NOT NULL when its type is.
     */
    public function column(string $name, Domain $domain, ?ColumnOrigin $origin, ?Charset $results = null): ResultColumn
    {
        $text = ($domain->kind === Kind::String || $domain->kind === Kind::Json || $domain->kind->temporal()) && !$domain->collation->bytes();
        $charset = $text ? ($results === null ? $domain->collation->id : $results->defaultCollation(GrammarRelease::MySql847)->id) : 63;
        $field = $domain->field === Field::Enum || $domain->field === Field::Set ? Field::String : $domain->field;
        $blob = in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);
        $length = $text && $results !== null ? $this->converted($blob ? ($domain->display ?? $domain->length) : $domain->length, $results->maxLength, $blob) : $domain->byteLength();

        $flags = $origin !== null && $origin->exact ? $origin->flags | ($domain->flags() & ColumnFlag::NotNull->value) : $domain->flags() | ($origin->flags ?? 0);
        if (($flags & ColumnFlag::ZeroFill->value) !== 0) {
            $flags &= ~ColumnFlag::Binary->value;
        }

        return new ResultColumn($name, $field, $length, $domain->decimals, $flags, $charset, $origin->column ?? '', $origin->table ?? '', $origin->originalTable ?? '', $origin->schema ?? '');
    }

    /**
     * Answers the bytes of a number of characters in a character set, at most what a length field holds.
     *
     * A longer string is sent with the most whole characters a length field holds, and a longer
     * BLOB or TEXT with the most bytes (verified on a live 8.4 server).
     *
     * @param bool $blob Whether the string is a BLOB or a TEXT
     */
    public function converted(int $characters, int $width, bool $blob = false): int
    {
        $bytes = $characters * $width;
        if ($bytes <= 4294967295) {
            return $bytes;
        }

        return $blob ? 4294967295 : intdiv(4294967295, $width) * $width;
    }

    /**
     * Writes a value of a domain in the text the server sends; a ZEROFILL YEAR column writes four digits.
     */
    public function text(int|float|string|null $value, Domain $domain, bool $zeroFill = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($zeroFill && $domain->kind === Kind::Year) {
            return sprintf('%04d', (int) $value);
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
