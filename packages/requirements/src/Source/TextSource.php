<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;
use RuntimeException;

final class TextSource implements SourceExtension
{
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        if (preg_match('/^lines:([1-9][0-9]*)(?:-([1-9][0-9]*))?$/D', $selector, $match) !== 1) {
            throw new RuntimeException('Text selectors use lines:START-END or lines:NUMBER.');
        }
        $start = (int) $match[1];
        $end = isset($match[2]) ? (int) $match[2] : $start;
        $lines = preg_split('/\r\n|\n|\r/', $this->loader->read($source, $directory, $live));
        if ($lines === false || $start > $end || $end > count($lines)) {
            throw new RuntimeException('Text line range is outside the source.');
        }
        $units = [];
        for ($i = $start; $i <= $end; ++$i) {
            $text = Unit::normalize($lines[$i - 1]);
            if ($text !== '') {
                $units[] = new Unit('line:' . $i, $text);
            }
        }
        return $units;
    }
}
