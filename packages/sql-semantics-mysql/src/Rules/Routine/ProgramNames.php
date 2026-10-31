<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Writes the names of stored programs and the labels of their statements.
 *
 * Rule: MYSQL-PROGRAM-NAMES-001. A program name is written with its
 * database qualifier when it has one; a label before a statement is
 * followed by a colon; a data type is followed by its COLLATE clause when
 * it has one. Terminates: constant work.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html,
 * https://dev.mysql.com/doc/refman/8.4/en/statement-labels.html.
 * Status: Implemented.
 *
 * @example Refusing an empty stored-program name
 *     \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::check(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name(''))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a trailing space
 *     \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::check(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p '))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a name beyond 64 characters
 *     \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::check(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name(str_repeat('p', 65)))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a NUL byte in a name
 *     \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::check(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name("p\0tail"))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProgramNames
{
    /**
     * Refuses a routine name whose decoded operand cannot name a stored program.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the name is empty, ends in a space, contains NUL or exceeds 64 characters
     */
    public static function check(QualifiedName $name): void
    {
        \SqlSemantics\Diagnostic\Check::input(!str_contains($name->name->value, "\0"), 'A stored-program name cannot contain NUL.');
        $problem = self::problem($name->name);
        \SqlSemantics\Diagnostic\Check::input($problem === null, $problem?->message() ?? 'A stored-program name is valid.');
    }

    /**
     * Answers the shared routine and modern parameter name condition.
     */
    public static function problem(Name $name): ?\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem
    {
        $rule = match (true) {
            $name->value === '', str_ends_with($name->value, ' ') => \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::IncorrectName,
            mb_strlen($name->value, 'UTF-8') > 64 => \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::NameTooLong,
            default => null,
        };

        return $rule === null ? null : new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem($rule, $name->value);
    }

    /**
     * Writes a name with its optional database qualifier.
     */
    public function qualified(Output $out, QualifiedName $name, NameUse $use = NameUse::Routine): void
    {
        if ($name->schema !== null) {
            $out->name($name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($name->name, $use);
    }

    /**
     * Writes the label that begins a block or loop, when there is one.
     */
    public function label(Output $out, ?Name $label): void
    {
        if ($label !== null) {
            $out->name($label, NameUse::Label)->glue()->symbol(':');
        }
    }

    /**
     * Writes the label that ends a block or loop, when there is one.
     */
    public function end(Output $out, ?Name $label): void
    {
        if ($label !== null) {
            $out->name($label, NameUse::Label);
        }
    }

    /**
     * Writes a data type and its COLLATE clause, when there is one.
     */
    public function type(Output $out, TypeName $type, ?CollationName $collation): void
    {
        $out->node($type);
        if ($collation !== null) {
            $out->keyword('COLLATE')->node($collation);
        }
    }
}
