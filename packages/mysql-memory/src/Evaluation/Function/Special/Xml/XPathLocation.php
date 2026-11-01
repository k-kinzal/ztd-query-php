<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

use Closure;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Frame;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The location paths of an XPath expression of EXTRACTVALUE and UPDATEXML: steps of an axis, a node test and predicates.
 *
 * A path is absolute after `/` or `//`, `/` alone being the root; `//` stands for a
 * descendant-or-self step, `.` for self and `..` for parent, `@` for the attribute axis, and a
 * step without an axis is on the child axis. A node test is `*`, a name, or a node type test such
 * as text() (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XPathLocation
{
    /**
     * The node type tests, which keep the nodes they are applied to.
     */
    public const TYPES = ['text', 'node', 'comment', 'processing-instruction'];

    /**
     * @param XPath $parser The parser of the expression the path is in, which reads the predicates
     */
    public function __construct(public readonly XPath $parser)
    {
    }

    /**
     * Reads a location path.
     *
     * @return array{string, Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand, string}
     *
     * @throws SqlError When the path does not read
     */
    public function location(): array
    {
        $reader = $this->parser->reader;
        $kind = $reader->peek()[0];
        $absolute = $kind === '/' || $kind === '//';
        if ($kind === '/') {
            $reader->next();
            $next = $reader->peek()[0];
            if (!in_array($next, ['.', '..', '@', '*', 'NAME', 'AXIS'], true)) {
                return [XPathOperand::NODES, static fn (): XPathOperand => XPathOperand::nodes([0]), '/'];
            }
            $steps = $this->relative();
        } elseif ($kind === '//') {
            $steps = $this->steps();
        } else {
            $steps = $this->relative();
        }

        return [XPathOperand::NODES, static fn (XmlDocument $d, Frame $f, Collation $c, int $n): XPathOperand => XPathAxes::walk($steps, [$absolute ? 0 : $n], $d, $f, $c), '?'];
    }

    /**
     * Reads the steps that follow a `/` or `//`, starting with that separator.
     *
     * @return list<array{string, string, list<Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand>}>
     *
     * @throws SqlError When a step does not read
     */
    public function steps(): array
    {
        [$separator] = $this->parser->reader->next();
        $steps = $separator === '//' ? [['descendant-or-self', '*', []]] : [];

        return [...$steps, ...$this->relative()];
    }

    /**
     * Reads a relative location path: steps separated by `/` or `//`.
     *
     * @return list<array{string, string, list<Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand>}>
     *
     * @throws SqlError When a step does not read
     */
    public function relative(): array
    {
        $reader = $this->parser->reader;
        $steps = [$this->step()];
        while (in_array($reader->peek()[0], ['/', '//'], true)) {
            [$separator] = $reader->next();
            if ($separator === '//') {
                $steps[] = ['descendant-or-self', '*', []];
            }
            $steps[] = $this->step();
        }

        return $steps;
    }

    /**
     * Reads one step: its axis, its node test and its predicates.
     *
     * @return array{string, string, list<Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand>}
     *
     * @throws SqlError When the step does not read
     */
    public function step(): array
    {
        $reader = $this->parser->reader;
        [$kind, $text] = $reader->peek();
        if ($kind === '.' || $kind === '..') {
            $reader->next();

            return [$kind === '.' ? 'self' : 'parent', '*', []];
        }
        $axis = 'child';
        if ($kind === '@') {
            $reader->next();
            $axis = 'attribute';
        } elseif ($kind === 'AXIS') {
            $reader->next();
            $axis = $text;
        }

        return [$axis, $this->test(), $this->predicates()];
    }

    /**
     * Reads the node test of a step: `*`, a name, or `()` for a node type test.
     *
     * @throws SqlError When the test does not read
     */
    public function test(): string
    {
        $reader = $this->parser->reader;
        [$kind, $text] = $reader->peek();
        if ($kind === '*') {
            $reader->next();

            return '*';
        }
        if ($kind !== 'NAME') {
            throw $reader->syntax();
        }
        $reader->next();
        if (!in_array($text, self::TYPES, true) || $reader->peek()[0] !== '(') {
            return $text;
        }
        $reader->next();
        if ($reader->peek()[0] !== ')') {
            throw $reader->syntax();
        }
        $reader->next();

        return '()';
    }

    /**
     * Reads the predicates of a step.
     *
     * @return list<Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand>
     *
     * @throws SqlError When a predicate does not read
     */
    public function predicates(): array
    {
        $reader = $this->parser->reader;
        $predicates = [];
        while ($reader->peek()[0] === '[') {
            $reader->next();
            $this->parser->depth++;
            [, $predicates[]] = $this->parser->expression();
            $this->parser->depth--;
            if ($reader->peek()[0] !== ']') {
                throw $reader->syntax();
            }
            $reader->next();
        }

        return $predicates;
    }
}
