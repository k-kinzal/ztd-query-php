<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access\Load;

/**
 * Reads LOAD XML records from attributes, child elements and named field elements.
 *
 * Parent fields remain visible in nested records. A record with no field event
 * repeats the last snapshot, including after an ignored row. Unclosed rows are
 * not emitted. These stream rules also accept fragments and mismatched closing
 * names, as observed on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-xml.html.
 *
 * @phpstan-type Frame array{label: string, row: bool, children: bool, text: bool, data: array<string, string|null>}
 * @visibility MySqlMemory
 */
final class XmlRecords
{
    /**
     * @var list<Frame> Open XML elements of this runtime file
     */
    public array $frames = [];

    /**
     * @var array<string, string|null> Fields visible at the last input event
     */
    public array $snapshot = [];

    /**
     * @return list<array<string, string|null>> Named records before binding to LOAD destinations
     */
    public function read(string $input, string $rowTag = '<row>', bool $legacy = false, int $ignored = 0): array
    {
        $this->frames = $this->snapshot = $records = [];
        $offset = 0;
        while (preg_match('~<!--.*?-->|<!\[CDATA\[.*?\]\]>|<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~s', $input, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $tag = $match[0][0];
            $offset = $match[0][1] + strlen($tag);
            if (str_starts_with($tag, '<!') || str_starts_with($tag, '<?')) {
                continue;
            }
            $completed = preg_match('~^</([^\s>]+)~', $tag, $closing) === 1
                ? $this->close($closing[1], $rowTag)
                : $this->open($tag, $input, $offset, $rowTag, $legacy);
            if ($completed) {
                $records[] = $this->snapshot;
            }
        }
        return array_slice($records, $ignored);
    }

    /**
     * Opens a field scope and answers whether a self-closing row completed.
     */
    public function open(string $tag, string $input, int &$offset, string $rowTag, bool $legacy): bool
    {
        $bytes = new XmlText();
        $fields = $bytes->attributes($tag);
        if ($fields === null || preg_match('~^<([^\s/>]+)~', $tag, $name) !== 1) {
            return false;
        }
        $row = '<' . $name[1] . '>' === $rowTag;
        $label = $name[1] === 'field' && isset($fields['name']) ? $fields['name'] : $name[1];
        if ($this->frames !== []) {
            $this->frames[count($this->frames) - 1]['children'] = true;
        }
        $this->frames[] = ['label' => $label, 'row' => $row, 'children' => false, 'text' => false, 'data' => $fields];
        if ($fields !== []) {
            $this->capture();
        }
        if (str_ends_with($tag, '/>')) {
            if (!$row) {
                $this->value(null);
            }
            array_pop($this->frames);
            return $row;
        }
        [$text, $offset] = $bytes->content($input, $offset, $legacy);
        if ($text !== '' && !$row) {
            $this->frames[count($this->frames) - 1]['text'] = true;
            $this->value($text);
        }
        return false;
    }

    /**
     * Closes one scope; the closing tag determines whether a row is emitted.
     */
    public function close(string $name, string $rowTag): bool
    {
        if ($this->frames === []) {
            return false;
        }
        $frame = $this->frames[count($this->frames) - 1];
        if (!$frame['row'] && !$frame['children'] && !$frame['text']) {
            $this->value(null);
        }
        array_pop($this->frames);
        return '<' . $name . '>' === $rowTag;
    }

    /**
     * Publishes a field value to its enclosing element and the current snapshot.
     */
    public function value(?string $value): void
    {
        \SqlSemantics\Diagnostic\Check::invariant($this->frames !== [], 'An XML field value requires an open element.');
        $top = count($this->frames) - 1;
        $label = $this->frames[$top]['label'];
        $this->frames[$top]['data'][$label] = $value;
        if (isset($this->frames[$top - 1])) {
            $this->frames[$top - 1]['data'][$label] = $value;
        }
        $this->capture();
    }

    /**
     * Combines visible ancestor fields with inner fields taking precedence.
     */
    public function capture(): void
    {
        $this->snapshot = [];
        foreach ($this->frames as $frame) {
            $this->snapshot = array_replace($this->snapshot, $frame['data']);
        }
    }
}
