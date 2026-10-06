<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyStream;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Statement\Query;

/**
 * Lowers COPY.
 *
 * Rule: PG-COPY-LOWER-001. Scope: `CopyStmt`, `copy_from`,
 * `copy_file_name`, `copy_delimiter`, `opt_using`, `opt_program`,
 * `opt_binary`; the options follow PG-COPY-OPTION-LOWER-001. Constructors:
 * `CopyTable`, `CopyQuery`, `CopyDirection`, `CopyStream`. USING before
 * DELIMITERS and WITH before the options are noise words.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CopyRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CopyStmt`.
     *
     * @throws ImplementationGap When the production has no rule, or the statement copied is not a query
     */
    public function copy(Node $statement): CopyTable|CopyQuery
    {
        $form = $this->lowering->productions->form($statement);
        $options = new CopyOptionRule($this->lowering);
        if ($form->signature === 'CopyStmt: COPY ( PreparableStmt ) TO opt_program copy_file_name opt_with copy_options') {
            $query = $this->lowering->manipulations->preparable($form->node(2));
            if (!$query instanceof Query) {
                throw ImplementationGap::rule('PG-COPY-LOWER-001: a copied statement that is not a query');
            }
            [$legacy, $generic] = $options->options($form->node(8));

            return new CopyQuery($query, $this->flag($form->node(5)), $this->file($form->node(6)), $legacy, $generic);
        }
        if ($form->signature !== 'CopyStmt: COPY opt_binary qualified_name opt_column_list copy_from opt_program copy_file_name copy_delimiter opt_with copy_options where_clause') {
            throw ImplementationGap::production($form);
        }
        [$legacy, $generic] = $options->options($form->node(9));

        return new CopyTable(
            $this->flag($form->node(1)),
            new TargetTable(new RelationReference($this->lowering->names->qualified($form->node(2)))),
            $this->lowering->names->names($form->node(3)),
            $this->direction($form->node(4)),
            $this->flag($form->node(5)),
            $this->file($form->node(6)),
            $this->delimiters($form->node(7)),
            $legacy,
            $generic,
            $this->lowering->queries->where($form->node(10)),
        );
    }

    /**
     * Lowers `copy_from`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function direction(Node $direction): CopyDirection
    {
        $form = $this->lowering->productions->form($direction);

        return match ($form->signature) {
            'copy_from: FROM' => CopyDirection::From,
            'copy_from: TO' => CopyDirection::To,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_binary` or `opt_program`: whether the keyword is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function flag(Node $flag): bool
    {
        $form = $this->lowering->productions->form($flag);

        return match ($form->signature) {
            'opt_binary: BINARY', 'opt_program: PROGRAM' => true,
            'opt_binary:', 'opt_program:' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `copy_file_name`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function file(Node $file): StringConstant|CopyStream
    {
        $form = $this->lowering->productions->form($file);

        return match ($form->signature) {
            'copy_file_name: Sconst' => $this->lowering->literals->string($form->node(0)),
            'copy_file_name: STDIN' => CopyStream::Stdin,
            'copy_file_name: STDOUT' => CopyStream::Stdout,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `copy_delimiter`: the string of DELIMITERS, or null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function delimiters(Node $clause): ?StringConstant
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'copy_delimiter:') {
            return null;
        }
        if ($form->signature !== 'copy_delimiter: opt_using DELIMITERS Sconst') {
            throw ImplementationGap::production($form);
        }
        $using = $this->lowering->productions->form($form->node(0));
        if ($using->signature !== 'opt_using: USING' && $using->signature !== 'opt_using:') {
            throw ImplementationGap::production($using);
        }

        return $this->lowering->literals->string($form->node(2));
    }
}
