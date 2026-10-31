<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Checks ENUM and SET members using the resolved collation and the current SQL mode.
 *
 * Members compare after trimming trailing spaces. Duplicates are errors under strict mode,
 * otherwise one note is recorded for each additional occurrence. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/constraint-enum.html.
 *
 * @visibility MySqlMemory
 */
final class EnumerationMembers
{
    /**
     * Resolves member bytes and character lengths in the declared column's character set.
     */
    public function members(Domain $domain, ?\SqlSemantics\Statement\Type\TypeDescriptor $type, \SqlSemantics\Contract\GrammarRelease $release): Domain
    {
        if ($type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Enumeration) {
            $encoded = (new \MySqlMemory\Typing\Declared($domain->collation, $release))->enumeration($type, $domain->collation);
            return new Domain($domain->kind, $domain->field, $encoded->length, $domain->decimals, $domain->unsigned, $domain->collation, $domain->nullable, $encoded->members, $domain->coercibility, $domain->numericBytes, $domain->display, $domain->quiet, $domain->source, $domain->capacity, $domain->displayDecimals, $domain->storageField);
        }
        $charset = $domain->collation->charset;
        if ($domain->members === [] || Encoding::utf8($charset)) {
            return $domain;
        }
        $members = array_map(static fn (string $member): string => Encoding::convert($member, Charset::known('utf8mb4'), $charset), $domain->members);
        $lengths = array_map(static fn (string $member): int => Encoding::length($member, $charset), $members);
        $length = $domain->field === Field::Enum ? max([0, ...$lengths]) : array_sum($lengths) + count($members) - 1;

        return new Domain($domain->kind, $domain->field, $length, $domain->decimals, $domain->unsigned, $domain->collation, $domain->nullable, $members, $domain->coercibility, $domain->numericBytes, $domain->display);
    }

    /**
     * Reports every member that has an equivalent later member, in definition order.
     *
     * @throws \MySqlMemory\Error\SqlError When a duplicate is found under strict SQL mode
     */
    public function check(Domain $domain, string $name, Context $context): void
    {
        if ($domain->field !== Field::Enum && $domain->field !== Field::Set) {
            return;
        }
        $members = [];
        $remaining = [];
        $charset = $domain->collation->charset;
        $utf8 = Charset::known('utf8mb4');
        $ordering = Ordering::of($domain->collation);
        foreach ($domain->members as $member) {
            $text = rtrim(Encoding::convert($member, $charset, $utf8), ' ');
            $key = $ordering->key(Encoding::convert($text, $utf8, $charset));
            $members[] = [$key, $text];
            $remaining[$key] = ($remaining[$key] ?? 0) + 1;
        }
        foreach ($members as [$key, $text]) {
            if (--$remaining[$key] === 0) {
                continue;
            }
            $kind = $domain->field === Field::Enum ? 'ENUM' : 'SET';
            if ($context->modes->strict()) {
                throw SchemaError::DuplicatedValueInType->error($name, $text, $kind);
            }
            $context->note(SchemaError::DuplicatedValueInType, $name, $text, $kind);
        }
    }
}
