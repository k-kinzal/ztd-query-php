<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Evaluation\Index\ArrayConversion;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Expands a stored row into the comparison entries of a composite index.
 *
 * A multi-valued part contributes one entry per converted JSON element.
 * Duplicate elements inside one row are harmless. SQL NULL and an empty
 * array produce no unique-key conflict with another row.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html#create-index-multi-valued.
 *
 * @visibility MySqlMemory
 */
final class IndexEntries
{
    /**
     * Recomputes virtual array entries when a write reads or removes an old row.
     *
     * Their conversion diagnostics belong to that access, even when the
     * document itself has not changed. Ordinary generated columns retain
     * the existing write path.
     *
     * @param list<int|float|string|null> $row
     * @return list<int|float|string|null>
     */
    public function refresh(array $row, TableDefinition $table, \MySqlMemory\Evaluation\Context $context, int $ordinal): array
    {
        $frame = new \MySqlMemory\Evaluation\Frame($context, $row);
        foreach ($table->columns as $position => $column) {
            if ($column->generated instanceof ArrayConversion) {
                array_splice($row, $position, 1, [$column->generated->at($frame, $ordinal)]);
            }
        }

        return $row;
    }

    /**
     * Answers comparison tokens for all non-NULL entries of a key in a row.
     *
     * @param list<int|float|string|null> $row The row including computed slots
     * @return list<string>
     */
    public function keys(array $row, Key $key, TableDefinition $table): array
    {
        $entries = [''];
        foreach ($key->columns as $index => $position) {
            $value = $row[$position];
            if ($value === null) {
                return [];
            }
            $column = $table->columns[$position];
            $conversion = $column->generated;
            $domain = $conversion instanceof ArrayConversion ? $conversion->elementDomain : $column->domain;
            $parts = $conversion instanceof ArrayConversion
                ? array_map(fn (JsonNode $entry): string => Order::key($this->value($entry, $domain), $domain), JsonNode::load((string) $value)->children())
                : [$this->part($value, $domain, $key->prefixes[$index] ?? null)];
            $expanded = [];
            foreach ($entries as $entry) {
                foreach ($parts as $part) {
                    $expanded[] = $entry . $part . "\0";
                }
            }
            $entries = $expanded;
        }

        return array_values(array_unique($entries));
    }

    /**
     * Reads a converted JSON element using its retained SQL domain.
     */
    public function value(JsonNode $entry, Domain $domain): int|float|string
    {
        if ($entry->type === \MySqlMemory\Value\Json\JsonKind::Opaque) {
            $decoded = base64_decode(explode(':', $entry->scalar(), 3)[2], true);
            \SqlSemantics\Diagnostic\Check::invariant($decoded !== false, 'An opaque index entry retains valid encoded bytes.');

            return $decoded;
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year => $domain->unsigned ? Integer::fromUnsignedText($entry->scalar()) : (int) $entry->scalar(),
            Kind::Double => is_float($entry->value) ? $entry->value : (float) $entry->scalar(),
            Kind::Decimal, Kind::Date, Kind::DateTime, Kind::Time, Kind::String, Kind::Bit, Kind::Json, Kind::Null => $entry->unquoted(),
        };
    }

    /**
     * Writes the converted array as a duplicate diagnostic, bounded by the entry storage width.
     * Verified on MySQL 8.4 for integer, binary, text, decimal and temporal entries.
     */
    public function shown(string $value, Domain $domain): string
    {
        $bytes = match ($domain->kind) {
            Kind::Integer, Kind::Double, Kind::Year => 8,
            Kind::Date => 3,
            Kind::Time => 3 + intdiv($domain->decimals + 1, 2),
            Kind::DateTime => 5 + intdiv($domain->decimals + 1, 2),
            Kind::Decimal => \MySqlMemory\Value\Json\JsonBinary::digits($domain->precision() - $domain->decimals) + \MySqlMemory\Value\Json\JsonBinary::digits($domain->decimals),
            Kind::String, Kind::Bit, Kind::Json, Kind::Null => $domain->length * $domain->collation->charset->maxLength,
        };

        return mb_strcut(JsonNode::load($value)->text(), 0, $bytes, 'UTF-8');
    }

    /**
     * Answers an ordinary part's comparison token after its optional prefix.
     */
    public function part(int|float|string $value, Domain $domain, ?int $prefix): string
    {
        if ($prefix !== null && $domain->kind === Kind::String) {
            $value = Encoding::slice((string) $value, 0, $prefix, $domain->collation->charset);
        }

        return Order::key($value, $domain);
    }
}
