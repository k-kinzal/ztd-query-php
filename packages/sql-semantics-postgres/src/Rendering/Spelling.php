<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rendering;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Writes the spellings several PostgreSQL structure values share.
 *
 * Rule: PG-SPELLING-001. A string constant is written between single quotes
 * with each quote doubled, which reads back as the same value under
 * `standard_conforming_strings = on`. A dotted name writes its first part
 * where the grammar reads a `ColId` and every later part where it reads a
 * `ColLabel`. Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Spelling
{
    /**
     * Spells a string value as a string constant.
     */
    public function string(string $value): string
    {
        Check::input(!str_contains($value, "\0"), 'A PostgreSQL string constant holds no zero byte.');

        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Writes name parts separated by dots; a single part is written for the given position.
     *
     * @param list<Name> $parts
     */
    public function dotted(Output $out, array $parts, NameUse $single = NameUse::Qualifier): void
    {
        foreach ($parts as $position => $part) {
            if ($position > 0) {
                $out->symbol('.');
            }
            $out->name($part, $position > 0 ? NameUse::Label : (count($parts) === 1 ? $single : NameUse::Qualifier));
        }
    }

    /**
     * Writes name parts separated by dots where the grammar reads every part as a `ColId`, as in a configuration parameter name.
     *
     * @param list<Name> $parts
     */
    public function columns(Output $out, array $parts): void
    {
        foreach ($parts as $position => $part) {
            if ($position > 0) {
                $out->symbol('.');
            }
            $out->name($part, NameUse::Qualifier);
        }
    }

    /**
     * Writes a relation name with its schema and catalog qualifiers.
     */
    public function qualified(Output $out, QualifiedName $name, NameUse $single = NameUse::Relation): void
    {
        $parts = [];
        if ($name->catalog !== null) {
            $parts[] = $name->catalog;
        }
        if ($name->schema !== null) {
            $parts[] = $name->schema;
        }
        $parts[] = $name->name;
        $this->dotted($out, $parts, $single);
    }
}
