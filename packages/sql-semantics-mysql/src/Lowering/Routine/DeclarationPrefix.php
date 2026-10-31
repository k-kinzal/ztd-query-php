<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Reads completed routine declaration types before constructing their public values.
 * Only a typed refusal and its boundary escape this input stage.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DeclarationPrefix
{
    private ?Node $returnType = null;

    /**
     * Observes a return type followed by COLLATE before an unfinished routine body.
     *
     * @param list<\SqlParser\Lexer\Token> $tokens The input tokens, used only to identify RETURNS
     */
    public function following(Node $node, Lowering $lowering, array $tokens): ?ParseFailure
    {
        if ($node->name === 'type') {
            $start = (new \SqlSemantics\Construction\SourceRanges())->span($node)[0] ?? 0;
            $previous = null;
            foreach ($tokens as $token) {
                if ($token->offset >= $start) {
                    break;
                }
                $previous = $token;
            }
            $this->returnType = $previous?->name === 'RETURNS_SYM' ? $node : null;
        }
        if ($node->name !== 'opt_collate' || $this->returnType === null) {
            return null;
        }
        $type = $this->returnType;
        $this->returnType = null;
        try {
            [$declared] = (new ParameterRule($lowering))->declared($type, $node);
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits())->problem($declared, '', $lowering->profile->grammar)
                ?? (new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramFacts())->returnCollation($declared, $lowering->profile->grammar);
            if ($problem !== null) {
                return new ParseFailure($problem, true);
            }
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, true);
        }

        return null;
    }

    /**
     * Answers the declaration's refusal before any later body or default expression.
     * A DECLARE statement's type is checked once the whole declaration is read, so its
     * refusal is placed after the DEFAULT value, as the server does.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($node->name === 'sp_name') {
            try {
                $lowering->names->qualified($node);
            } catch (RejectedInput $failure) {
                return $failure;
            }

            return null;
        }
        if (!in_array($node->name, ['type_with_opt_collate', 'sp_fdparam', 'sp_pdparam', 'sp_decl', 'sf_tail'], true)) {
            return null;
        }
        $type = null;
        $collation = null;
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['type', 'type_with_opt_collate'], true)) {
                $type = $child;
            } elseif ($child instanceof Node && $child->name === 'opt_collate') {
                $collation = $child;
            }
        }
        if ($type === null) {
            return null;
        }
        try {
            (new ParameterRule($lowering))->declared($type, $collation);
        } catch (RejectedInput $failure) {
            if ($node->name === 'sp_decl' && $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize) {
                return new RejectedInput($failure->problem, (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? $failure->end, $failure->aborts, $failure->sources);
            }

            return $failure;
        }

        return null;
    }

    /**
     * Preserves the failure ahead of subsequent deprecation warnings.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        $failure = $this->rejected($node, $lowering);

        return $failure === null ? null : new ParseFailure($failure->problem, true);
    }
}
