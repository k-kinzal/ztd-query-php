<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use Closure;
use MySqlMemory\Error\SqlError;
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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
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
     * FOUND_ROWS() includes rows skipped by OFFSET. SQL_CALC_FOUND_ROWS also counts
     * the remaining input. Block limits retain that count through final projections;
     * an outer set-operation limit can instead be consumed here.
     *
     * @param bool $calculate Whether the query asks for SQL_CALC_FOUND_ROWS
     * @param Closure(ResultSet): void|null $failed Retains rows already produced by a failing query
     * @param Closure(): list<ResultSet>|null $interrupted Takes results emitted inside a caller row
     *
     * @throws SqlError When computing a row fails
     */
    public function result(QueryPlan $plan, Context $context, bool $calculate = false, ?Closure $failed = null, ?Closure $interrupted = null): ResultSet
    {
        $limit = $calculate && $plan->root instanceof Limit && $plan->root->countFound && !$plan->root->exhaust ? $plan->root : null;
        $iterator = (new Builder())->build($limit === null ? $plan->root : $limit->input);
        $context->row = 1;
        $width = count($plan->domains);
        $results = $context->variables->read('character_set_results');
        $charset = is_string($results) ? Charset::named($results) : null;
        $release = GrammarRelease::from('mysql-' . $context->variables->instance->version);
        $resultCollation = $context->variables->resultCollation();
        $rows = [];
        $binaryValues = [];
        $interruptions = new \MySqlMemory\Result\Interleaving($interrupted);
        $found = 0;
        $frame = new Frame($context);
        try {
            $iterator->init($frame);
            while (($row = $iterator->read()) !== null) {
                $interruptions->collect(count($rows));
                $found++;
                $context->row = $found + 1;
                if ($limit !== null && ($found <= $limit->offset || ($limit->count !== null && $found > $limit->offset + $limit->count))) {
                    continue;
                }
                $values = [];
                for ($i = 0; $i < $width; $i++) {
                    $values[] = $this->sent($this->text($row[$i] ?? null, $plan->domains[$i], (($plan->origins[$i]->flags ?? 0) & ColumnFlag::ZeroFill->value) !== 0), $plan->domains[$i], $charset);
                }
                $special = array_filter(array_slice($row, 0, $width), static fn ($value): bool => is_float($value) && !is_finite($value));
                if ($special !== []) {
                    $binaryValues[count($rows)] = $special;
                }
                $rows[] = $values;
            }
        } catch (SqlError $error) {
            $interruptions->collect(count($rows));
            if ($failed !== null) {
                $failed(new ResultSet($this->columns($plan, $charset, $release, $resultCollation), $rows, $context->diagnostics->raised(), false, interruptions: $interruptions->results, binaryValues: $binaryValues));
            }
            throw $error;
        }
        $context->variables->foundRows = $frame->foundBeforeProjection ?? $found;
        $interruptions->collect(count($rows));

        return new ResultSet($this->columns($plan, $charset, $release, $resultCollation), $rows, $context->diagnostics->raised(), interruptions: $interruptions->results, binaryValues: $binaryValues);
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
    public function columns(QueryPlan $plan, ?Charset $results = null, GrammarRelease $release = GrammarRelease::MySql847, ?Collation $resultCollation = null): array
    {
        $columns = [];
        foreach ($plan->domains as $position => $domain) {
            $origin = $plan->origins[$position] ?? null;
            $name = $plan->names[$position] ?? '';
            $columns[] = $this->column($results === null || Encoding::utf8($results) ? $name : Encoding::convert($name, Charset::known('utf8mb4'), $results), $domain, $origin, $results, $release, $resultCollation);
        }

        return $columns;
    }

    /**
     * Answers the definition of one column.
     *
     * A string is sent converted to the character set of the results, so its length counts the
     * bytes of its characters in that set; a set without results keeps the column's own.
     * A TEXT/BLOB byte bound is divided by its source character set's minimum width
     * before conversion, including UCS-2 and UTF-16. A
     * temporal value in a character set, as a rollup item is, is sent as such a string. A
     * ZEROFILL column carries no BINARY flag. A numeric stored column also carries no BINARY flag; standalone numeric
     * expressions retain it. A column of a system table carries the flags the
     * server reports for it, and NOT NULL when its type is.
     */
    public function column(string $name, Domain $domain, ?ColumnOrigin $origin, ?Charset $results = null, GrammarRelease $release = GrammarRelease::MySql847, ?Collation $resultCollation = null): ResultColumn
    {
        $text = in_array($domain->kind, [Kind::String, Kind::Json, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year], true) && !$domain->collation->bytes();
        $charset = $text ? $this->collation($domain, $results, $release, $resultCollation)->id : 63;
        $field = $domain->field === Field::Enum || $domain->field === Field::Set ? Field::String : $domain->field;
        $blob = in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);
        $characters = $this->characters($domain, $blob);
        $length = $text && $results !== null ? $this->converted($characters, $results->maxLength, $blob) : $domain->byteLength();

        $flags = $origin !== null && $origin->exact ? $origin->flags | ($domain->flags() & ColumnFlag::NotNull->value) : $domain->flags() | ($origin->flags ?? 0);
        if (($flags & ColumnFlag::ZeroFill->value) !== 0 || ($origin !== null && !$origin->exact && $domain->kind->numeric())) {
            $flags &= ~ColumnFlag::Binary->value;
        }

        $encode = static fn (string $value): string => $results === null ? $value : Encoding::convert($value, Charset::known('utf8mb4'), $results);

        return new ResultColumn($name, $field, $length, $domain->displayDecimals ?? $domain->decimals, $flags, $charset, $encode($origin->column ?? ''), $encode($origin->table ?? ''), $encode($origin->originalTable ?? ''), $encode($origin->schema ?? ''));
    }

    /**
     * Retains a column's held collation when result conversion is disabled; otherwise uses the selected result encoding.
     */
    public function collation(Domain $domain, ?Charset $results, GrammarRelease $release, ?Collation $resultCollation): Collation
    {
        return $results === null ? $domain->collation : ($resultCollation ?? $results->defaultCollation($release));
    }

    /**
     * Computes the character bound before result conversion. JSON holds a byte
     * bound, ordinary strings a character bound, and blobs use their minimum width.
     */
    public function characters(Domain $domain, bool $blob): int
    {
        return $blob ? intdiv($domain->display ?? $domain->length, $domain->collation->charset->minLength()) : intdiv($domain->display ?? ($domain->length * ($domain->kind === Kind::Json ? 1 : $domain->collation->charset->maxLength)), $domain->collation->charset->maxLength);
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
     * Writes a value in the text the server sends, including declared ZEROFILL padding.
     */
    public function text(int|float|string|null $value, Domain $domain, bool $zeroFill = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) && !is_finite($value) && $domain->kind === Kind::Double) {
            return '0';
        }
        if ($zeroFill && $domain->kind === Kind::Year) {
            return sprintf('%04d', (int) $value);
        }
        if ($domain->kind === Kind::Bit) {
            return $domain->field === Field::Bit ? (string) $value : \MySqlMemory\Value\Integer::text(Convert::bits((string) $value), true);
        }

        $text = $domain->kind === Kind::Double && $domain->field === Field::Float && $domain->decimals >= Domain::NOT_FIXED ? Real::format((float) sprintf('%.6G', (float) $value)) : Convert::toText($value, $domain);

        return $text !== null && $zeroFill && $domain->kind->numeric() ? str_pad($text, $domain->display ?? $domain->length, '0', STR_PAD_LEFT) : $text;
    }
}
