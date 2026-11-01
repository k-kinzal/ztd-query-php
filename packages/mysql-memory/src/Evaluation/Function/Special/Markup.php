<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Special\Xml\XmlDocument;
use MySqlMemory\Evaluation\Function\Special\Xml\XPath;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathOperand;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Typing\Domain;

/**
 * The XML functions: EXTRACTVALUE and UPDATEXML.
 *
 * The XPath must be known when the statement is compiled, else the call is refused ("Only
 * constant XPATH queries are supported"); it is read then, so an XPath that does not read fails
 * the statement even when no row is read. A fragment that does not read is NULL with the warning
 * ER_WRONG_VALUE naming the problem, a warning even where a strict mode makes warnings errors. EXTRACTVALUE answers the texts directly in the nodes the XPath
 * selects, joined by spaces, or the text of the value it computes; UPDATEXML replaces the markup of
 * the one node the XPath selects, answers the fragment unchanged when it selects none or several,
 * and NULL when it computes no node set (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Markup
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('EXTRACTVALUE', 2, 2, fn (Frame $f, array $a, Domain $r): ?string => $this->extract($f, $a, $r), 1, $this->prepare(...), true),
            new Routine('UPDATEXML', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->update($f, $a, $r), 1, $this->prepare(...), true),
        ];
    }

    /**
     * Reads the XPath when the call is compiled: it must be known, and read.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known Whether each argument is known when the call is compiled
     * @return list<Evaluable>
     *
     * @throws SqlError When the XPath is not known, or does not read
     */
    public function prepare(Frame $frame, array $arguments, array $known): array
    {
        if (!($known[1] ?? false)) {
            throw StatementError::UnknownError->error('Only constant XPATH queries are supported');
        }
        $path = $arguments[1]->evaluate($frame);
        if ($path !== null) {
            XPath::compile((string) \MySqlMemory\Evaluation\Convert::toText($path, $arguments[1]->domain()));
        }

        return $arguments;
    }

    /**
     * Reads the fragment and computes the XPath over it, or answers null when an argument is NULL or the fragment does not read.
     *
     * @param list<Evaluable> $arguments
     * @return array{XmlDocument, XPathOperand}|null
     *
     * @throws SqlError When the XPath fails while it computes
     */
    public function evaluate(Frame $frame, array $arguments, Domain $result): ?array
    {
        $strings = new Strings();
        $xml = $strings->text($frame, $arguments[0], $result);
        $path = $strings->text($frame, $arguments[1], $result);
        if ($xml === null || $path === null) {
            return null;
        }
        $compiled = XPath::compile($path);
        $document = XmlDocument::read($xml);
        if (is_string($document)) {
            $frame->context->diagnostics->warning(DataError::WrongValue, DataError::WrongValue->message('XML', $document));

            return null;
        }

        return [$document, $compiled($document, $frame, $result->collation, 0, 1, 1)];
    }

    /**
     * EXTRACTVALUE(xml, xpath).
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the XPath fails while it computes
     */
    public function extract(Frame $frame, array $arguments, Domain $result): ?string
    {
        $computed = $this->evaluate($frame, $arguments, $result);

        return $computed === null ? null : $computed[1]->text($computed[0]);
    }

    /**
     * UPDATEXML(xml, xpath, replacement).
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the XPath fails while it computes
     */
    public function update(Frame $frame, array $arguments, Domain $result): ?string
    {
        $computed = $this->evaluate($frame, $arguments, $result);
        $replacement = (new Strings())->text($frame, $arguments[2], $result);
        if ($computed === null || $replacement === null) {
            return null;
        }
        [$document, $value] = $computed;
        if ($value->kind !== XPathOperand::NODES) {
            return null;
        }
        if (count($value->nodes) !== 1) {
            return $document->text;
        }
        [, , , $start, $end] = $document->nodes[$value->nodes[0]];

        return substr($document->text, 0, $start) . $replacement . substr($document->text, $end);
    }
}
