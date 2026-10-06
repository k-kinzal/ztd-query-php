<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\LargeObjectNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TransformFor;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TypeReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;

/**
 * Lowers object kinds and the object names of the generic object commands.
 *
 * Rule: PG-OBJECT-LOWER-001. Scope: `object_type_any_name`,
 * `object_type_name`, `drop_type_name`, `object_type_name_on_any_name`, and
 * the object part of `CommentStmt`, `SecLabelStmt`, `RenameStmt`,
 * `AlterObjectSchemaStmt`, `AlterOwnerStmt` and `AlterObjectDependsStmt`.
 * Constructors: `ObjectKind` cases and the object references of
 * PG-OBJECT-FORM-001. The object part is read from the nonterminal or
 * keyword that starts it, so one reader serves every production of these
 * commands; the commands claim their productions. PROCEDURAL before LANGUAGE
 * is a noise word. Termination: a fixed number of children is read.
 * Source: https://www.postgresql.org/docs/17/sql-comment.html, https://www.postgresql.org/docs/17/sql-drop-owned.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ObjectRule
{
    /**
     * The kind each kind production writes.
     */
    private const KINDS = [
        'object_type_any_name: TABLE' => 'TABLE', 'object_type_any_name: SEQUENCE' => 'SEQUENCE', 'object_type_any_name: VIEW' => 'VIEW',
        'object_type_any_name: MATERIALIZED VIEW' => 'MATERIALIZED VIEW', 'object_type_any_name: INDEX' => 'INDEX', 'object_type_any_name: FOREIGN TABLE' => 'FOREIGN TABLE',
        'object_type_any_name: COLLATION' => 'COLLATION', 'object_type_any_name: CONVERSION_P' => 'CONVERSION', 'object_type_any_name: STATISTICS' => 'STATISTICS',
        'object_type_any_name: TEXT_P SEARCH PARSER' => 'TEXT SEARCH PARSER', 'object_type_any_name: TEXT_P SEARCH DICTIONARY' => 'TEXT SEARCH DICTIONARY',
        'object_type_any_name: TEXT_P SEARCH TEMPLATE' => 'TEXT SEARCH TEMPLATE', 'object_type_any_name: TEXT_P SEARCH CONFIGURATION' => 'TEXT SEARCH CONFIGURATION',
        'object_type_name: DATABASE' => 'DATABASE', 'object_type_name: ROLE' => 'ROLE', 'object_type_name: SUBSCRIPTION' => 'SUBSCRIPTION', 'object_type_name: TABLESPACE' => 'TABLESPACE',
        'drop_type_name: ACCESS METHOD' => 'ACCESS METHOD', 'drop_type_name: EVENT TRIGGER' => 'EVENT TRIGGER', 'drop_type_name: EXTENSION' => 'EXTENSION',
        'drop_type_name: FOREIGN DATA_P WRAPPER' => 'FOREIGN DATA WRAPPER', 'drop_type_name: PUBLICATION' => 'PUBLICATION', 'drop_type_name: SCHEMA' => 'SCHEMA',
        'drop_type_name: SERVER' => 'SERVER',
        'object_type_name_on_any_name: POLICY' => 'POLICY', 'object_type_name_on_any_name: RULE' => 'RULE', 'object_type_name_on_any_name: TRIGGER' => 'TRIGGER',
    ];

    /**
     * The kind each keyword that starts a named routine writes.
     */
    private const ROUTINES = ['FUNCTION' => ObjectKind::Function, 'PROCEDURE' => ObjectKind::Procedure, 'ROUTINE' => ObjectKind::Routine];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `object_type_any_name`, `object_type_name`, `drop_type_name` or `object_type_name_on_any_name`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function kind(Node $kind): ObjectKind
    {
        $form = $this->lowering->productions->form($kind);
        if ($form->signature === 'object_type_name: drop_type_name') {
            return $this->kind($form->node(0));
        }
        if ($form->signature === 'drop_type_name: opt_procedural LANGUAGE') {
            $this->lowering->flags->present($form->node(0));

            return ObjectKind::Language;
        }

        return ObjectKind::from(self::KINDS[$form->signature] ?? throw ImplementationGap::production($form));
    }

    /**
     * Tells whether the child at a position is the given terminal.
     */
    public function token(Form $form, int $position, string $terminal): bool
    {
        $child = $form->node->children[$position] ?? null;

        return $child instanceof Token && $child->name === $terminal;
    }

    /**
     * Tells whether the production writes the given terminal anywhere.
     */
    public function has(Form $form, string $terminal): bool
    {
        foreach ($form->node->children as $child) {
            if ($child instanceof Token && $child->name === $terminal) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the position of the object part of an ALTER production: the first nonterminal after ALTER other than PROCEDURAL.
     *
     * @throws ImplementationGap When the production has no object part
     */
    public function start(Form $form): int
    {
        foreach ($form->node->children as $position => $child) {
            if ($position > 0 && $child instanceof Node && $child->name !== 'opt_procedural') {
                return $position;
            }
        }

        throw ImplementationGap::production($form);
    }

    /**
     * Lowers the object part of a COMMENT or SECURITY LABEL production that starts at a position.
     *
     * @return array{ObjectKind, ObjectReference}
     *
     * @throws ImplementationGap When the object part has no rule
     */
    public function named(Form $form, int $at): array
    {
        $child = $form->node->children[$at] ?? null;
        $names = $this->lowering->names;
        if ($child instanceof Node) {
            return match ($child->name) {
                'object_type_any_name' => [$this->kind($child), $names->dotted($form->node($at + 1))],
                'object_type_name' => [$this->kind($child), new UnqualifiedName($names->name($form->node($at + 1)))],
                'object_type_name_on_any_name' => [$this->kind($child), new MemberName($names->name($form->node($at + 1)), $names->dotted($form->node($at + 3)))],
                default => throw ImplementationGap::production($form),
            };
        }

        return $this->keyed($form, $at);
    }

    /**
     * Lowers an object part of COMMENT or SECURITY LABEL that starts with a keyword.
     *
     * @return array{ObjectKind, ObjectReference}
     *
     * @throws ImplementationGap When the object part has no rule
     */
    public function keyed(Form $form, int $at): array
    {
        $names = $this->lowering->names;
        $types = $this->lowering->types;
        $routines = new SignatureRule($this->lowering);
        $word = $form->token($at)->name;
        $domain = $this->token($form, $at + 3, 'DOMAIN_P');

        return match ($word) {
            'COLUMN' => [ObjectKind::Column, $names->dotted($form->node($at + 1))],
            'TYPE_P' => [ObjectKind::Type, new TypeReference($types->typeName($form->node($at + 1)))],
            'DOMAIN_P' => [ObjectKind::Domain, new TypeReference($types->typeName($form->node($at + 1)))],
            'AGGREGATE' => [ObjectKind::Aggregate, $routines->aggregate($form->node($at + 1))],
            'FUNCTION', 'PROCEDURE', 'ROUTINE' => [self::ROUTINES[$word], $routines->function($form->node($at + 1))],
            'OPERATOR' => $this->operator($form, $at),
            'CONSTRAINT' => [$domain ? ObjectKind::DomainConstraint : ObjectKind::Constraint, new MemberName($names->name($form->node($at + 1)), $names->dotted($form->node($at + ($domain ? 4 : 3))), $domain)],
            'TRANSFORM' => [ObjectKind::Transform, new TransformFor($types->typeName($form->node($at + 2)), $names->name($form->node($at + 4)))],
            'LARGE_P' => [ObjectKind::LargeObject, new LargeObjectNumber($this->lowering->literals->signed($form->node($at + 2)))],
            'CAST' => [ObjectKind::Cast, new CastPair($types->typeName($form->node($at + 2)), $types->typeName($form->node($at + 4)))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an object part that starts with OPERATOR: an operator, an operator class or an operator family.
     *
     * @return array{ObjectKind, ObjectReference}
     */
    public function operator(Form $form, int $at): array
    {
        if ($this->token($form, $at + 1, 'CLASS') || $this->token($form, $at + 1, 'FAMILY')) {
            $kind = $this->token($form, $at + 1, 'CLASS') ? ObjectKind::OperatorClass : ObjectKind::OperatorFamily;

            return [$kind, new OperatorGroupName($this->lowering->names->dotted($form->node($at + 2)), $this->lowering->names->name($form->node($at + 4)))];
        }

        return [ObjectKind::Operator, (new SignatureRule($this->lowering))->operator($form->node($at + 1))];
    }

    /**
     * Lowers the object part of an ALTER production that starts at a position, and answers the position after it.
     *
     * @return array{ObjectReference, int}
     *
     * @throws ImplementationGap When the object part has no rule
     */
    public function altered(Form $form, int $at): array
    {
        $node = $form->node($at);
        $names = $this->lowering->names;
        $routines = new SignatureRule($this->lowering);
        if ($node->name === 'any_name' && $this->token($form, $at + 1, 'USING')) {
            return [new OperatorGroupName($names->dotted($node), $names->name($form->node($at + 2))), $at + 3];
        }
        if ($node->name === 'name' && $this->token($form, $at + 1, 'ON')) {
            return [new MemberName($names->name($node), $names->qualified($form->node($at + 2))), $at + 3];
        }

        return match ($node->name) {
            'function_with_argtypes' => [$routines->function($node), $at + 1],
            'aggregate_with_argtypes' => [$routines->aggregate($node), $at + 1],
            'operator_with_argtypes' => [$routines->operator($node), $at + 1],
            'any_name' => [$names->dotted($node), $at + 1],
            'name' => [new UnqualifiedName($names->name($node)), $at + 1],
            'RoleId' => [new UnqualifiedName($this->lowering->roles->name($node)), $at + 1],
            'relation_expr' => [new RelationTarget($this->lowering->queries->relation($node)), $at + 1],
            'qualified_name' => [new RelationTarget(new RelationReference($names->qualified($node))), $at + 1],
            'NumericOnly' => [new LargeObjectNumber($this->lowering->literals->signed($node)), $at + 1],
            default => throw ImplementationGap::production($form),
        };
    }
}
