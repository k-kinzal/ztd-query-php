<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Hint;

use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;

/**
 * Reads the text of one hint comment into its hints, as the hint parser of the server does.
 *
 * Rule: MYSQL-OPTIMIZER-HINTS-002. The comment holds one or more hints,
 * separated by whitespace only, read from the tokens of HintLexer. Hint
 * names and strategy names are keywords and cannot name a table, an index
 * or a block. A query block is `@name` right after the opening parenthesis
 * of a hint that takes one, or `name@block` right after a table name of a
 * hint without one, with no whitespace around `@`. A SET_VAR value is a
 * number up to 2^64 - 1, a decimal, a name or a string. SEMIJOIN takes a
 * comma after its block. The first problem ends the comment: the offset
 * reported is the start of the text not taken, and the position right
 * after `@` for a block name that does not follow it or that the hint does
 * not take there (verified on live 5.7.44 and 8.4 servers). Terminates:
 * every step consumes input.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Hint
 */
final class HintParser
{
    /**
     * The largest number a SET_VAR value takes, 2^64 - 1.
     */
    public const SIZE = '18446744073709551615';

    /**
     * @param HintLexer $lexer The tokens of the text between `/*+` and the end of the comment
     */
    public function __construct(public readonly HintLexer $lexer)
    {
    }

    /**
     * Reads the hints of the text, up to its first problem.
     */
    public function parse(): HintComment
    {
        $hints = [];
        try {
            do {
                $hints[] = $this->hint();
            } while ($this->lexer->token()[0] !== 'end');
        } catch (HintRefusal $refusal) {
            return new HintComment($hints, $refusal->error);
        }

        return new HintComment($hints);
    }

    /**
     * Reads one hint: its name and its arguments in parentheses.
     *
     * @throws HintRefusal When the text is not a hint
     */
    public function hint(): OptimizerHint
    {
        [$kind, $value, $start, $end] = $this->lexer->token();
        $name = $kind === 'keyword' ? HintName::tryFrom($value) : null;
        if ($name === null) {
            throw $this->lexer->refuse($start);
        }
        $this->lexer->at = $end;
        $this->symbol('(');
        $form = $name->form();
        [$next, $symbol, $at] = $this->lexer->token();
        if ($next === 'symbol' && $symbol === '@' && in_array($form, [HintForm::ExecutionTime, HintForm::ResourceGroup, HintForm::Variable, HintForm::BlockName], true)) {
            throw $this->lexer->refuse($at + 1);
        }

        return match ($form) {
            HintForm::Table, HintForm::JoinOrder => $this->tables($name),
            HintForm::FixedOrder => $this->fixed($name),
            HintForm::Key => $this->key($name),
            HintForm::Semijoin, HintForm::Subquery => $this->strategies($name),
            HintForm::ExecutionTime => $this->time(),
            HintForm::ResourceGroup => new ResourceGroupHint($this->close($this->name(false))),
            HintForm::Variable => $this->variable(),
            HintForm::BlockName => new BlockNameHint($this->close($this->name(false))),
        };
    }

    /**
     * Reads the arguments of a table-level or join order hint: an optional block and tables separated by commas.
     *
     * @throws HintRefusal When the arguments are not well formed
     */
    public function tables(HintName $name): TableHint
    {
        $block = $this->block();
        $tables = [];
        $next = $this->lexer->token();
        if ($next[0] !== 'symbol' || $next[1] !== ')') {
            $tables[] = $this->table($block === null);
            $next = $this->lexer->token();
            while ($next[0] === 'symbol' && $next[1] === ',') {
                $this->lexer->at = $next[3];
                $tables[] = $this->table($block === null);
                $next = $this->lexer->token();
            }
        }
        $this->symbol(')');

        return new TableHint($name, $block, $tables);
    }

    /**
     * Reads the arguments of JOIN_FIXED_ORDER: an optional block.
     *
     * @throws HintRefusal When the arguments are not well formed
     */
    public function fixed(HintName $name): TableHint
    {
        $block = $this->block();
        $this->symbol(')');

        return new TableHint($name, $block, []);
    }

    /**
     * Reads the arguments of an index-level hint: an optional block, a table, and indexes separated by commas.
     *
     * @throws HintRefusal When the arguments are not well formed
     */
    public function key(HintName $name): KeyHint
    {
        $block = $this->block();
        $table = $this->table($block === null);
        $indexes = [];
        if ($this->lexer->token()[0] === 'name') {
            $indexes[] = $this->name(false);
            $next = $this->lexer->token();
            while ($next[0] === 'symbol' && $next[1] === ',') {
                $this->lexer->at = $next[3];
                $indexes[] = $this->name(false);
                $next = $this->lexer->token();
            }
        }
        $this->symbol(')');

        return new KeyHint($name, $block, $table, $indexes);
    }

    /**
     * Reads the arguments of a subquery hint: an optional block, and strategies separated by commas.
     *
     * @throws HintRefusal When the arguments are not well formed
     */
    public function strategies(HintName $name): StrategyHint
    {
        $block = $this->block();
        $subquery = $name->form() === HintForm::Subquery;
        $taken = $subquery ? StrategyHint::SUBQUERY : StrategyHint::SEMIJOIN;
        $next = $this->lexer->token();
        $comma = $block !== null && !$subquery && $next[0] === 'symbol' && $next[1] === ',';
        if ($comma) {
            $this->lexer->at = $next[3];
        }
        $strategies = [];
        while (true) {
            [$kind, $value, $start, $end] = $this->lexer->token();
            if ($kind !== 'keyword' || !in_array($value, $taken, true)) {
                if ($strategies === [] && !$subquery && !$comma && $kind === 'symbol' && $value === ')') {
                    break;
                }
                throw $this->lexer->refuse($start);
            }
            $this->lexer->at = $end;
            $strategies[] = $value;
            $next = $this->lexer->token();
            if ($subquery || $next[0] !== 'symbol' || $next[1] !== ',') {
                break;
            }
            $this->lexer->at = $next[3];
        }
        $this->symbol(')');

        return new StrategyHint($name, $block, $strategies);
    }

    /**
     * Reads the argument of MAX_EXECUTION_TIME: a number of milliseconds the server supports.
     *
     * @throws HintRefusal When the argument is not a number, or a number the server does not support
     */
    public function time(): ExecutionTimeHint
    {
        [$kind, $value, $start, $end] = $this->lexer->token();
        if ($kind !== 'integer') {
            throw $this->lexer->refuse($start);
        }
        $this->lexer->at = $end;
        $close = $this->lexer->token();
        if ($close[0] === 'symbol' && $close[1] === ')' && !self::supported($value)) {
            throw $this->lexer->refuse($close[2], HintFailure::ExecutionTime);
        }
        $this->symbol(')');

        return new ExecutionTimeHint($value);
    }

    /**
     * Reads the arguments of SET_VAR: a variable, `=` and a value.
     *
     * @throws HintRefusal When the arguments are not well formed
     */
    public function variable(): VariableHint
    {
        $variable = $this->name(false);
        $this->symbol('=');
        [$kind, $value, $start, $end] = $this->lexer->token();
        $read = match ($kind) {
            'integer' => self::compare($value, self::SIZE) <= 0 ? new HintLiteral(HintLiteralKind::Integer, $value) : throw $this->lexer->refuse($start, HintFailure::Size),
            'decimal' => new HintLiteral(HintLiteralKind::Decimal, $value),
            'text' => new HintLiteral(HintLiteralKind::Text, $value),
            'name' => null,
            default => throw $this->lexer->refuse($start),
        };
        if ($read === null) {
            return new VariableHint($variable, new HintLiteral(HintLiteralKind::Word, $this->close($this->name(false))));
        }
        $this->lexer->at = $end;
        $this->symbol(')');

        return new VariableHint($variable, $read);
    }

    /**
     * Reads the block a hint writes right after its opening parenthesis, `@name`, or answers null when it writes none.
     *
     * @throws HintRefusal When the block name does not follow `@` at once
     */
    public function block(): ?string
    {
        [$kind, $value, $start] = $this->lexer->token();
        if ($kind !== 'symbol' || $value !== '@') {
            return null;
        }

        return $this->after($start + 1);
    }

    /**
     * Reads a table: a name, and `@block` right after it when the hint writes no leading block.
     *
     * @param bool $suffixed Whether the table may name its block
     *
     * @throws HintRefusal When the text is not a table
     */
    public function table(bool $suffixed): HintTable
    {
        [$kind, $value, $start, $end] = $this->lexer->token();
        if ($kind !== 'name') {
            throw $this->lexer->refuse($start);
        }
        $this->lexer->at = $end;
        if (($this->lexer->text[$end] ?? '') !== '@') {
            return new HintTable($value);
        }
        if (!$suffixed) {
            throw $this->lexer->refuse($end + 1);
        }

        return new HintTable($value, $this->after($end + 1));
    }

    /**
     * Reads a name; a name directly followed by `@` is refused right after the `@` unless the caller reads the block.
     *
     * @param bool $suffixed Whether the caller reads a block after the name
     *
     * @throws HintRefusal When the text is not a name
     */
    public function name(bool $suffixed): string
    {
        [$kind, $value, $start, $end] = $this->lexer->token();
        if ($kind !== 'name') {
            throw $this->lexer->refuse($start);
        }
        $this->lexer->at = $end;
        if (!$suffixed && ($this->lexer->text[$end] ?? '') === '@') {
            throw $this->lexer->refuse($end + 1);
        }

        return $value;
    }

    /**
     * Reads the block name that starts right at a position after `@`; any word, keywords included, names a block.
     *
     * @throws HintRefusal When no name starts there
     */
    public function after(int $at): string
    {
        [$kind, $value, , $end] = $this->lexer->scan($at, false);
        if ($kind !== 'name' && $kind !== 'keyword') {
            throw $this->lexer->refuse($at);
        }
        $this->lexer->at = $end;

        return $value;
    }

    /**
     * Reads the closing parenthesis after a value and answers the value.
     *
     * @throws HintRefusal When the parenthesis does not follow
     */
    public function close(string $value): string
    {
        $this->symbol(')');

        return $value;
    }

    /**
     * Reads one symbol.
     *
     * @throws HintRefusal When the next token is not the symbol
     */
    public function symbol(string $symbol): void
    {
        [$kind, $value, $start, $end] = $this->lexer->token();
        if ($kind !== 'symbol' || $value !== $symbol) {
            throw $this->lexer->refuse($start);
        }
        $this->lexer->at = $end;
    }

    /**
     * Tells whether the server supports a MAX_EXECUTION_TIME limit: up to 2^32 - 1, or from 2^63 to 2^64 - 1.
     *
     * @example Telling the limits apart
     *     [\SqlSemantics\Platform\MySql\Lowering\Hint\HintParser::supported('4294967295'), \SqlSemantics\Platform\MySql\Lowering\Hint\HintParser::supported('4294967296'), \SqlSemantics\Platform\MySql\Lowering\Hint\HintParser::supported('9223372036854775808')] // => [true, false, true]
     */
    public static function supported(string $digits): bool
    {
        return self::compare($digits, '4294967295') <= 0 || (self::compare($digits, '9223372036854775808') >= 0 && self::compare($digits, self::SIZE) <= 0);
    }

    /**
     * Compares two numbers written as decimal digits without leading zeros.
     */
    public static function compare(string $left, string $right): int
    {
        return strlen($left) === strlen($right) ? strcmp($left, $right) <=> 0 : strlen($left) <=> strlen($right);
    }
}
