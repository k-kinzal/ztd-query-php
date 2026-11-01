<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

use Closure;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The steps of an XPath location path of EXTRACTVALUE and UPDATEXML, applied to a document.
 *
 * The axes are child, descendant, descendant-or-self, parent, ancestor, ancestor-or-self, self and
 * attribute, the other axes reading as child; a node type test such as text() keeps the nodes it is
 * applied to; the descendant axes reach elements only; last() is the size of the whole step;
 * positions on the ancestor axes count down from the number of ancestors met, repeats included. A
 * predicate that is a number keeps the node at that position, a string being read as a number
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XPathAxes
{
    /**
     * Applies steps to the nodes they start from and answers the nodes they reach.
     *
     * @param list<array{string, string, list<Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand>}> $steps
     * @param list<int> $nodes
     */
    public static function walk(array $steps, array $nodes, XmlDocument $document, Frame $frame, Collation $collation): XPathOperand
    {
        foreach ($steps as [$axis, $test, $predicates]) {
            $entries = self::axis($axis, $test, $nodes, $document);
            foreach ($predicates as $predicate) {
                $entries = self::filter($entries, $predicate, $document, $frame, $collation);
            }
            $nodes = XPathOperand::nodes(array_map(static fn (array $entry): int => $entry[0], $entries))->nodes;
        }

        return XPathOperand::nodes($nodes);
    }

    /**
     * Keeps the nodes of a step a predicate holds for, and numbers them again within the nodes they were reached from.
     *
     * @param list<array{int, int, int, int}> $entries Each node with its position, the size of the step and the node it was reached from
     * @param Closure(XmlDocument, Frame, Collation, int, int, int): XPathOperand $predicate
     * @return list<array{int, int, int, int}>
     */
    public static function filter(array $entries, Closure $predicate, XmlDocument $document, Frame $frame, Collation $collation): array
    {
        $kept = [];
        foreach ($entries as [$node, $position, $size, $group]) {
            $value = $predicate($document, $frame, $collation, $node, $position, $size);
            $keep = match ($value->kind) {
                XPathOperand::INTEGER, XPathOperand::DOUBLE => (int) round((float) $value->value, 0, PHP_ROUND_HALF_EVEN) === $position,
                XPathOperand::STRING => ($value->quiet ? (int) XPathFunctions::prefix((string) $value->value) : Convert::toInteger((string) $value->value, Domain::string(0, $collation), $frame->context)) === $position,
                XPathOperand::NULL => false,
                default => $value->holds($document, $frame, $collation) === true,
            };
            if ($keep) {
                $kept[] = [$node, $group];
            }
        }
        $entries = [];
        $counts = [];
        foreach ($kept as [$node, $group]) {
            $counts[$group] = ($counts[$group] ?? 0) + 1;
            $entries[] = [$node, $counts[$group], count($kept), $group];
        }

        return $entries;
    }

    /**
     * Answers the nodes an axis reaches from some nodes: each with its position, the size of the step and the node it was reached from.
     *
     * @param list<int> $nodes
     * @return list<array{int, int, int, int}>
     */
    public static function axis(string $axis, string $test, array $nodes, XmlDocument $document): array
    {
        if ($test === '()') {
            return array_map(static fn (int $node): array => [$node, 1, count($nodes), $node], $nodes);
        }
        if ($axis === 'parent' || $axis === 'ancestor' || $axis === 'ancestor-or-self') {
            return self::ancestors($axis, $test, $nodes, $document);
        }
        $reached = [];
        foreach ($nodes as $node) {
            $found = match ($axis) {
                'self' => $document->nodes[$node][0] === XmlDocument::ROOT || self::matches($node, XmlDocument::ELEMENT, $test, $document) || self::matches($node, XmlDocument::ATTRIBUTE, $test, $document) ? [$node] : [],
                'attribute' => array_values(array_filter($document->children[$node] ?? [], static fn (int $child): bool => self::matches($child, XmlDocument::ATTRIBUTE, $test, $document))),
                'descendant', 'descendant-or-self' => self::descendants($node, $axis === 'descendant-or-self', $test, $document),
                default => array_values(array_filter($document->children[$node] ?? [], static fn (int $child): bool => self::matches($child, XmlDocument::ELEMENT, $test, $document))),
            };
            foreach ($found as $position => $child) {
                $reached[] = [$child, $position + 1, 0, $node];
            }
        }

        return array_map(static fn (array $entry): array => [$entry[0], $entry[1], count($reached), $entry[3]], $reached);
    }

    /**
     * Answers the elements the parent, ancestor or ancestor-or-self axis reaches from some nodes, in document order.
     *
     * The positions on the ancestor axes count down from the number of ancestors met, repeats included.
     *
     * @param list<int> $nodes
     * @return list<array{int, int, int, int}>
     */
    public static function ancestors(string $axis, string $test, array $nodes, XmlDocument $document): array
    {
        $count = 0;
        $active = [];
        foreach ($nodes as $node) {
            $current = $axis === 'ancestor-or-self' ? $node : $document->nodes[$node][2];
            while ($current >= 0) {
                if (self::matches($current, XmlDocument::ELEMENT, $test, $document)) {
                    $active[$current] = true;
                    $count++;
                }
                $current = $axis === 'parent' ? -1 : $document->nodes[$current][2];
            }
        }
        ksort($active);
        $reached = [];
        $index = 0;
        foreach (array_keys($active) as $node) {
            $reached[] = [$node, $axis === 'parent' ? ++$index : $count - $index++, $axis === 'parent' ? count($active) : $count, 0];
        }

        return $reached;
    }

    /**
     * Tells whether a node is of a kind and passes a name test.
     */
    public static function matches(int $node, int $kind, string $test, XmlDocument $document): bool
    {
        return $document->nodes[$node][0] === $kind && ($test === '*' || $document->nodes[$node][1] === $test);
    }

    /**
     * Answers the elements below a node in document order, the node first when it is asked for and matches.
     *
     * @return list<int>
     */
    public static function descendants(int $node, bool $self, string $test, XmlDocument $document): array
    {
        $found = [];
        if ($self && ($document->nodes[$node][0] === XmlDocument::ROOT ? $test === '*' : self::matches($node, XmlDocument::ELEMENT, $test, $document))) {
            $found[] = $node;
        }
        foreach ($document->children[$node] ?? [] as $child) {
            if ($document->nodes[$child][0] === XmlDocument::ELEMENT) {
                $found = [...$found, ...self::descendants($child, true, $test, $document)];
            }
        }

        return $found;
    }
}
