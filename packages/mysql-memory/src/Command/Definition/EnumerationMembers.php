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
