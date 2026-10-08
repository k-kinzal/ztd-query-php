<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

use Closure;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Frame;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The XPath of EXTRACTVALUE and UPDATEXML: reads an expression into a closure over a document.
 *
 * The subset is the one the manual documents, with the server's own readings: or, and, the
 * equality and relational operators, `+`, `-`, `*`, div, mod, unary minus, unions of node sets,
 * location paths, which a parenthesized node set may start, user variables written `$@name`,
 * literals and the functions of XPathFunctions. Literals are integers and doubles of fixed
 * decimals; an integer beyond BIGINT UNSIGNED reads as its largest value, and one beyond BIGINT
 * wraps as the server's does. Two node sets do not compare, a side of `|` must be a node set,
 * last() and position() stand only in a predicate, and a variable of a stored program is unknown.
 * A node set is true when it holds exactly one node. An expression that does not read is
 * "XPATH syntax error" quoting the text from the problem on (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XPath
{
    /**
     * The closures of the expressions read, by text.
     *
     * @var array<string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand>
     */
    public static array $cache = [];

    /**
     * The depth of the predicates the reader is in.
     */
    public int $depth = 0;

    /**
     * @param XPathReader $reader The tokens of the expression
     */
    public function __construct(public readonly XPathReader $reader)
    {
    }

    /**
     * Reads an expression into its kind and a closure that computes it.
     *
     * @return Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand
     *
     * @throws SqlError When the expression does not read
     */
    public static function compile(string $text): Closure
    {
        if (isset(self::$cache[$text])) {
            return self::$cache[$text];
        }
        $parser = new self(new XPathReader($text));
        [, $closure] = $parser->expression();
        if ($parser->reader->peek()[0] !== 'END') {
            throw $parser->reader->syntax();
        }
        if (count(self::$cache) > 256) {
            self::$cache = [];
        }

        return self::$cache[$text] = $closure;
    }

    /**
     * Reads `expr`: an or-expression.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read
     */
    public function expression(): array
    {
        $left = $this->conjunction();
        while ($this->reader->peek()[0] === 'NAME' && $this->reader->peek()[1] === 'or') {
            $this->reader->next();
            $right = $this->conjunction();
            $left = XPathOperators::logic($left, $right, false);
        }

        return $left;
    }

    /**
     * Reads an and-expression.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read
     */
    public function conjunction(): array
    {
        $left = $this->comparison(['=', '!=']);
        while ($this->reader->peek()[0] === 'NAME' && $this->reader->peek()[1] === 'and') {
            $this->reader->next();
            $right = $this->comparison(['=', '!=']);
            $left = XPathOperators::logic($left, $right, true);
        }

        return $left;
    }

    /**
     * Reads an equality or a relational expression.
     *
     * @param list<string> $operators The operators of the level
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read, or compares two node sets
     */
    public function comparison(array $operators): array
    {
        $relational = $operators === ['<', '<=', '>', '>='];
        $left = $relational ? $this->additive() : $this->comparison(['<', '<=', '>', '>=']);
        while (in_array($this->reader->peek()[0], $operators, true)) {
            [$operator, , $at] = $this->reader->next();
            $right = $relational ? $this->additive() : $this->comparison(['<', '<=', '>', '>=']);
            if ($left[0] === XPathOperand::NODES && $right[0] === XPathOperand::NODES) {
                $this->reader->blank();

                throw new SqlError(StatementError::UnknownError, "XPATH error: comparison of two nodesets is not supported: '" . substr($this->reader->text, $at) . "'", null, [[1105, "XPATH syntax error: '" . substr($this->reader->text, $this->reader->at) . "'"]]);
            }
            $first = $left[1];
            $second = $right[1];
            $left = [XPathOperand::BOOLEAN, static fn (XmlDocument $d, Frame $f, Collation $c, int $n, int $p, int $s): XPathOperand => XPathOperators::compare($first($d, $f, $c, $n, $p, $s), $second($d, $f, $c, $n, $p, $s), $operator, $d, $f, $c), '?'];
        }

        return $left;
    }

    /**
     * Reads an additive expression.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read
     */
    public function additive(): array
    {
        $left = $this->multiplicative();
        while (in_array($this->reader->peek()[0], ['+', '-'], true)) {
            [$operator] = $this->reader->next();
            $left = XPathOperators::arithmetic($left, $this->multiplicative(), $operator);
        }

        return $left;
    }

    /**
     * Reads a multiplicative expression.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read
     */
    public function multiplicative(): array
    {
        $left = $this->unary();
        while (true) {
            $token = $this->reader->peek();
            if ($token[0] !== '*' && !($token[0] === 'NAME' && in_array($token[1], ['div', 'mod'], true))) {
                return $left;
            }
            $this->reader->next();
            $left = XPathOperators::arithmetic($left, $this->unary(), $token[1]);
        }
    }

    /**
     * Reads a unary expression.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read
     */
    public function unary(): array
    {
        if ($this->reader->peek()[0] !== '-') {
            return $this->union();
        }
        $this->reader->next();

        return XPathOperators::negate($this->unary());
    }

    /**
     * Reads a union of paths.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read, or a side of `|` is no node set
     */
    public function union(): array
    {
        $left = $this->path();
        while ($this->reader->peek()[0] === '|') {
            $this->reader->next();
            if ($left[0] !== XPathOperand::NODES) {
                throw $this->reader->syntax();
            }
            $right = $this->path();
            if ($right[0] !== XPathOperand::NODES) {
                throw $this->reader->syntax();
            }
            $first = $left[1];
            $second = $right[1];
            $left = [XPathOperand::NODES, static fn (XmlDocument $d, Frame $f, Collation $c, int $n, int $p, int $s): XPathOperand => XPathOperand::nodes([...$first($d, $f, $c, $n, $p, $s)->nodes, ...$second($d, $f, $c, $n, $p, $s)->nodes]), '?'];
        }

        return $left;
    }

    /**
     * Reads a path: a location path, or a primary expression that a relative path may follow.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read
     */
    public function path(): array
    {
        [$kind, $text] = $this->reader->peek();
        $call = $kind === 'NAME' && isset(XPathFunctions::FUNCTIONS[$text]) && $this->reader->after($text) === '(';
        if (!$call && in_array($kind, ['$', '(', 'STRING', 'NUMBER'], true) === false) {
            return (new XPathLocation($this))->location();
        }
        $primary = $this->primary();
        if (!in_array($this->reader->peek()[0], ['/', '//'], true)) {
            return $primary;
        }
        if ($primary[0] !== XPathOperand::NODES) {
            throw $this->reader->syntax();
        }
        $steps = (new XPathLocation($this))->steps();
        $start = $primary[1];

        return [XPathOperand::NODES, static fn (XmlDocument $d, Frame $f, Collation $c, int $n, int $p, int $s): XPathOperand => XPathAxes::walk($steps, $start($d, $f, $c, $n, $p, $s)->nodes, $d, $f, $c), '?'];
    }

    /**
     * Reads a primary expression: a variable, a parenthesized expression, a literal or a function call.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the expression does not read, or names a variable of a stored program
     */
    public function primary(): array
    {
        [$kind, $text, $at] = $this->reader->next();
        if ($kind === '(') {
            $inner = $this->expression();
            if ($this->reader->peek()[0] !== ')') {
                throw $this->reader->syntax();
            }
            $this->reader->next();

            return $inner;
        }
        if ($kind === 'STRING') {
            return [XPathOperand::STRING, static fn (): XPathOperand => new XPathOperand(XPathOperand::STRING, $text), "'" . $text . "'"];
        }
        if ($kind === 'NUMBER') {
            return $this->number($text);
        }
        if ($kind === '$') {
            return $this->variable($at);
        }

        return $this->call($text);
    }

    /**
     * Reads a user variable after its `$`: `$@name`; any other variable is one of a stored program, which is unknown.
     *
     * @param int $at Where the `$` starts
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the variable does not read, or is not a user variable
     */
    public function variable(int $at): array
    {
        $reader = $this->reader;
        if (($reader->text[$reader->at] ?? '') !== '@') {
            $reader->at += strspn($reader->text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_', $reader->at);
            $reader->blank();

            throw new SqlError(StatementError::UnknownError, "Unknown XPATH variable at: '" . substr($reader->text, $at) . "'", null, [[1105, "XPATH syntax error: '" . substr($reader->text, $reader->at) . "'"]]);
        }
        if (preg_match('/\G@([A-Za-z0-9_$]+)/', $reader->text, $name, 0, $reader->at) !== 1) {
            $reader->at++;
            throw $reader->syntax();
        }
        $reader->at += strlen($name[0]);

        return ['any', static fn (XmlDocument $d, Frame $f): XPathOperand => XPathFunctions::variable($f, $name[1]), '@' . $name[1]];
    }

    /**
     * Reads a number literal: an integer, or a double with as many decimals as it writes.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     */
    public function number(string $text): array
    {
        if (str_contains($text, '.')) {
            $decimals = strlen(explode('.', $text)[1] ?? '');

            return [XPathOperand::DOUBLE, static fn (): XPathOperand => new XPathOperand(XPathOperand::DOUBLE, (float) $text, [], $decimals), $text];
        }
        $digits = ltrim($text, '0');
        $unsigned = strlen($digits) > 20 || (strlen($digits) === 20 && strcmp($digits, '18446744073709551615') > 0) ? '18446744073709551615' : ($digits === '' ? '0' : $digits);
        $value = (strlen($unsigned) < 19 || (strlen($unsigned) === 19 && strcmp($unsigned, '9223372036854775807') <= 0)) ? (int) $unsigned : PHP_INT_MIN + 10000000000 + ((int) substr($unsigned, 0, -10) - 922337204) * 10000000000 + ((int) substr($unsigned, -10) - 6854775808);

        return [XPathOperand::INTEGER, static fn (): XPathOperand => new XPathOperand(XPathOperand::INTEGER, $value), (string) $value];
    }

    /**
     * Reads the arguments of a function call and answers what it computes.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the call does not read
     */
    public function call(string $name): array
    {
        $this->reader->next();
        $arguments = [];
        $starts = [];
        if ($this->reader->peek()[0] !== ')') {
            while (true) {
                $starts[] = $this->reader->peek()[2];
                $arguments[] = $this->expression();
                if ($this->reader->peek()[0] !== ',') {
                    break;
                }
                $this->reader->next();
            }
        }
        [$fewest, $most] = XPathFunctions::FUNCTIONS[$name];
        if ($most >= 0 && count($arguments) > $most) {
            throw $this->reader->syntax($starts[$most]);
        }
        if ($this->reader->peek()[0] !== ')') {
            throw $this->reader->syntax();
        }
        if (count($arguments) < $fewest) {
            throw $this->reader->syntax();
        }
        $this->reader->next();
        if ((in_array($name, ['count', 'sum'], true) && $arguments[0][0] !== XPathOperand::NODES) || ($name === 'string-length' && $arguments === []) || (in_array($name, ['last', 'position'], true) && $this->depth === 0)) {
            throw $this->reader->syntax();
        }
        $closures = array_map(static fn (array $argument): Closure => $argument[1], $arguments);

        return [$name === 'concat' || $name === 'substring' ? XPathOperand::STRING : XPathOperand::DOUBLE, static fn (XmlDocument $d, Frame $f, Collation $c, int $n, int $p, int $s): XPathOperand => XPathFunctions::apply($name, array_map(static fn (Closure $argument): XPathOperand => $argument($d, $f, $c, $n, $p, $s), $closures), $d, $f, $c, $p, $s), $name . '()'];
    }
}
