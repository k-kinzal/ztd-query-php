<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Core\Catalog\StatementPart;
use SqlFormatter\Core\FormattingException;

/**
 * Formats report SQL with sql-formatter's expanded layout.
 *
 * Catalog entries do not carry a dialect, so each supported grammar is tried.
 * Gaps are temporarily represented by identifiers and restored with their
 * provenance after formatting. Text no grammar accepts is kept as written.
 *
 * @visibility root
 */
final class SqlFormatter
{
    /**
     * @param list<\SqlCatalog\Core\Reporter\SqlFormatter> $formatters Ordered application policies
     */
    public function __construct(private readonly array $formatters = [])
    {
    }

    /**
     * The statement laid out for reading, with gap metadata preserved.
     *
     * @param list<StatementPart> $parts
     * @return list<StatementPart>
     */
    public function format(array $parts): array
    {
        [$sql, $gaps] = $this->mask($parts);
        if ($sql === '' || array_filter($parts, static fn (StatementPart $part): bool => !$part->isGap) === []) {
            return $parts;
        }
        foreach ($this->formatters as $formatter) {
            try {
                $formatted = $formatter->format($sql);
                if ($formatted !== null) {
                    return $this->restore($formatted, $gaps);
                }
            } catch (FormattingException) {
                continue;
            }
        }

        return $parts;
    }

    /**
     * Replaces gaps with identifiers that cannot collide with known SQL text.
     *
     * @param list<StatementPart> $parts
     * @return array{string, array<string, StatementPart>}
     */
    public function mask(array $parts): array
    {
        $known = implode('', array_map(static fn (StatementPart $part): string => $part->text, $parts));
        $prefix = '__sql_catalog_gap_';
        while (str_contains($known, $prefix)) {
            $prefix .= '_';
        }
        $sql = '';
        $gaps = [];
        foreach ($parts as $part) {
            if (!$part->isGap) {
                $sql .= $part->text;
                continue;
            }
            $marker = $prefix . count($gaps) . '__';
            $gaps[$marker] = $part;
            $sql .= $marker;
        }

        return $this->maskParameters($sql, $gaps, $prefix);
    }

    /**
     * Protects client placeholders that are not part of the server grammar.
     *
     * @param array<string, StatementPart> $gaps
     * @return array{string, array<string, StatementPart>}
     */
    public function maskParameters(string $sql, array $gaps, string $prefix): array
    {
        preg_match_all(SqlHighlighter::TOKENS, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $offset = 0;
        $masked = '';
        foreach ($matches as $match) {
            [$placeholder, $position] = $match['ph'] ?? ['', -1];
            if ($placeholder === '' || !in_array($placeholder[0], [':', '%'], true)
                || ($position > 0 && $sql[$position - 1] === ':')) {
                continue;
            }
            $marker = $prefix . count($gaps) . '__';
            $gaps[$marker] = new StatementPart($placeholder);
            $masked .= substr($sql, $offset, $position - $offset) . $marker;
            $offset = $position + strlen($placeholder);
        }

        return [$masked . substr($sql, $offset), $gaps];
    }

    /**
     * Restores gaps and client placeholders without interpreting literal gap-like text.
     *
     * @param array<string, StatementPart> $gaps
     * @return list<StatementPart>
     * @throws FormattingException When a marker cannot be restored
     */
    public function restore(string $sql, array $gaps): array
    {
        foreach ($gaps as $marker => $gap) {
            if (!str_contains($sql, $marker)) {
                throw new FormattingException('Formatting lost a SQL catalog gap.');
            }
        }
        if ($gaps === []) {
            return [new StatementPart($sql)];
        }
        $pattern = '/(' . implode('|', array_map(static fn (string $marker): string => preg_quote($marker, '/'), array_keys($gaps))) . ')/';
        $pieces = preg_split($pattern, $sql, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($pieces === false) {
            throw new FormattingException('Could not restore SQL catalog gaps.');
        }

        return array_map(static fn (string $piece): StatementPart => $gaps[$piece] ?? new StatementPart($piece), $pieces);
    }
}
