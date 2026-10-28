<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rendering;

use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Rendering\Output;

/**
 * Writes complete LOAD input and assignment roles in language order.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LoadRendering
{
    /**
     * Writes every retained clause without substituting assignment operands.
     */
    public function render(LoadTable $load, Output $out): void
    {
        $out->keyword('LOAD', $load->format->value);
        if ($load->lock !== null) {
            $out->keyword($load->lock->value);
        }
        $out->node($load->input);
        if ($load->duplicates !== null) {
            $out->keyword($load->duplicates->value);
        }
        $out->keyword('INTO', 'TABLE')->node($load->table);
        if ($load->charset !== null) {
            $out->keyword('CHARSET')->node($load->charset);
        }
        if ($load->compression !== null) {
            $out->keyword('COMPRESSION')->symbol('=')->node($load->compression);
        }
        if ($load->rowTag !== null) {
            $out->keyword('ROWS', 'IDENTIFIED', 'BY')->node($load->rowTag);
        }
        if ($load->fields !== []) {
            $out->keyword('COLUMNS');
        }
        foreach ($load->fields as $option) {
            $out->node($option);
        }
        if ($load->lines !== []) {
            $out->keyword('LINES');
        }
        foreach ($load->lines as $option) {
            $out->node($option);
        }
        if ($load->ignored !== null) {
            $out->keyword('IGNORE')->node($load->ignored)->keyword('LINES');
        }
        if ($load->columns !== null) {
            $out->symbol('(')->list($load->columns)->symbol(')');
        }
        if ($load->assignments !== []) {
            $out->keyword('SET')->list($load->assignments);
        }
        $out->node($load->bulk);
    }
}
