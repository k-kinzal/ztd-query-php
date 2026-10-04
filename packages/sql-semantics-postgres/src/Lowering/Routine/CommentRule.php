<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Comment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SecurityLabel;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;

/**
 * Lowers COMMENT ON and SECURITY LABEL.
 *
 * Rule: PG-COMMENT-LOWER-001. Scope: `CommentStmt`, `comment_text`,
 * `SecLabelStmt`, `opt_provider`, `security_label`. Constructors: `Comment`,
 * `SecurityLabel`; the object part is read by PG-OBJECT-LOWER-001. NULL
 * removes the comment or label. Source: https://www.postgresql.org/docs/17/sql-comment.html,
 * https://www.postgresql.org/docs/17/sql-security-label.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CommentRule
{
    /**
     * The productions of COMMENT ON.
     */
    private const COMMENTS = [
        'CommentStmt: COMMENT ON object_type_any_name any_name IS comment_text',
        'CommentStmt: COMMENT ON COLUMN any_name IS comment_text',
        'CommentStmt: COMMENT ON object_type_name name IS comment_text',
        'CommentStmt: COMMENT ON TYPE_P Typename IS comment_text',
        'CommentStmt: COMMENT ON DOMAIN_P Typename IS comment_text',
        'CommentStmt: COMMENT ON AGGREGATE aggregate_with_argtypes IS comment_text',
        'CommentStmt: COMMENT ON FUNCTION function_with_argtypes IS comment_text',
        'CommentStmt: COMMENT ON OPERATOR operator_with_argtypes IS comment_text',
        'CommentStmt: COMMENT ON CONSTRAINT name ON any_name IS comment_text',
        'CommentStmt: COMMENT ON CONSTRAINT name ON DOMAIN_P any_name IS comment_text',
        'CommentStmt: COMMENT ON object_type_name_on_any_name name ON any_name IS comment_text',
        'CommentStmt: COMMENT ON PROCEDURE function_with_argtypes IS comment_text',
        'CommentStmt: COMMENT ON ROUTINE function_with_argtypes IS comment_text',
        'CommentStmt: COMMENT ON TRANSFORM FOR Typename LANGUAGE name IS comment_text',
        'CommentStmt: COMMENT ON OPERATOR CLASS any_name USING name IS comment_text',
        'CommentStmt: COMMENT ON OPERATOR FAMILY any_name USING name IS comment_text',
        'CommentStmt: COMMENT ON LARGE_P OBJECT_P NumericOnly IS comment_text',
        'CommentStmt: COMMENT ON CAST ( Typename AS Typename ) IS comment_text',
    ];

    /**
     * The productions of SECURITY LABEL.
     */
    private const LABELS = [
        'SecLabelStmt: SECURITY LABEL opt_provider ON object_type_any_name any_name IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON COLUMN any_name IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON object_type_name name IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON TYPE_P Typename IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON DOMAIN_P Typename IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON AGGREGATE aggregate_with_argtypes IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON FUNCTION function_with_argtypes IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON LARGE_P OBJECT_P NumericOnly IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON PROCEDURE function_with_argtypes IS security_label',
        'SecLabelStmt: SECURITY LABEL opt_provider ON ROUTINE function_with_argtypes IS security_label',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CommentStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function comment(Node $statement): Comment
    {
        $form = $this->lowering->productions->form($statement);
        if (!in_array($form->signature, self::COMMENTS, true)) {
            throw ImplementationGap::production($form);
        }
        [$kind, $object] = (new ObjectRule($this->lowering))->named($form, 2);

        return new Comment($kind, $object, $this->text($form->node(count($form->node->children) - 1)));
    }

    /**
     * Lowers `SecLabelStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function label(Node $statement): SecurityLabel
    {
        $form = $this->lowering->productions->form($statement);
        if (!in_array($form->signature, self::LABELS, true)) {
            throw ImplementationGap::production($form);
        }
        [$kind, $object] = (new ObjectRule($this->lowering))->named($form, 4);

        return new SecurityLabel($kind, $object, $this->text($form->node(count($form->node->children) - 1)), $this->provider($form->node(2)));
    }

    /**
     * Lowers `comment_text` or `security_label`; NULL is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function text(Node $text): ?StringConstant
    {
        $form = $this->lowering->productions->form($text);

        return match ($form->signature) {
            'comment_text: Sconst', 'security_label: Sconst' => $this->lowering->literals->string($form->node(0)),
            'comment_text: NULL_P', 'security_label: NULL_P' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_provider`; no provider is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function provider(Node $provider): Word|StringConstant|null
    {
        $form = $this->lowering->productions->form($provider);

        return match ($form->signature) {
            'opt_provider:' => null,
            'opt_provider: FOR NonReservedWord_or_Sconst' => $this->lowering->options->wordOrString($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
