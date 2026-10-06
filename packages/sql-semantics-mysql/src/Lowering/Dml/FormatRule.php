<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;

/**
 * Lowers the text file format of LOAD DATA and SELECT ... INTO OUTFILE.
 *
 * Rule: MYSQL-DML-FORMAT-001. Scope: opt_load_data_charset, opt_field_term,
 * field_term_list, field_term, opt_line_term, line_term_list, line_term.
 * The options are kept in written order (FieldOption, LineOption); the
 * keyword FIELDS is the lexer's COLUMNS. Terminates: lists are flattened by
 * MYSQL-DML-LIST-001. Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class FormatRule
{
    /**
     * The field options by production.
     */
    private const FIELDS = [
        'field_term: TERMINATED BY text_string' => FieldOptionKind::Terminated, 'field_term: OPTIONALLY ENCLOSED BY text_string' => FieldOptionKind::OptionallyEnclosed,
        'field_term: ENCLOSED BY text_string' => FieldOptionKind::Enclosed, 'field_term: ESCAPED BY text_string' => FieldOptionKind::Escaped,
    ];

    /**
     * The line options by production.
     */
    private const LINES = ['line_term: TERMINATED BY text_string' => LineOptionKind::Terminated, 'line_term: STARTING BY text_string' => LineOptionKind::Starting];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the character set clause: a node of `opt_load_data_charset`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function charset(Node $charset): ?CharsetName
    {
        $form = $this->lowering->form($charset);
        if ($form->signature === 'opt_load_data_charset:') {
            return null;
        }
        if ($form->signature !== 'opt_load_data_charset: charset charset_name_or_default' && $form->signature !== 'opt_load_data_charset: character_set charset_name') {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->skip($form->node(0));

        return $this->lowering->charsets->charset($form->node(1));
    }

    /**
     * Lowers the FIELDS clause: a node of `opt_field_term`; an absent clause is empty.
     *
     * @return list<FieldOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function fields(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_field_term:') {
            return [];
        }
        if ($form->signature !== 'opt_field_term: COLUMNS field_term_list') {
            throw ImplementationGap::production($form);
        }
        $options = [];
        foreach ((new ListRule($this->lowering))->items($form->node(1), ['field_term_list: field_term_list field_term', 'field_term_list: field_term']) as $item) {
            $term = $this->lowering->form($item);
            $kind = self::FIELDS[$term->signature] ?? throw ImplementationGap::production($term);
            $options[] = new FieldOption($kind, $this->lowering->literals->text($term->node(count($term->node->children) - 1)));
        }

        return $options;
    }

    /**
     * Lowers the LINES clause: a node of `opt_line_term`; an absent clause is empty.
     *
     * @return list<LineOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function lines(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_line_term:') {
            return [];
        }
        if ($form->signature !== 'opt_line_term: LINES line_term_list') {
            throw ImplementationGap::production($form);
        }
        $options = [];
        foreach ((new ListRule($this->lowering))->items($form->node(1), ['line_term_list: line_term_list line_term', 'line_term_list: line_term']) as $item) {
            $term = $this->lowering->form($item);
            $kind = self::LINES[$term->signature] ?? throw ImplementationGap::production($term);
            $options[] = new LineOption($kind, $this->lowering->literals->text($term->node(2)));
        }

        return $options;
    }
}
