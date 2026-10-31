<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use Throwable;

/**
 * Every generated statement must reconstruct the same Compact SQL from its semantic values.
 */
final class RoundTripTarget
{
    private readonly Formatter $formatter;

    public function __construct(private readonly Semantics $semantics, private readonly string $grammarVersion)
    {
        $this->formatter = new Formatter($semantics->parser(), new FormatOptions(Style::Compact));
    }

    /**
     * Records rejections, implementation gaps, invariant violations and Compact differences as findings.
     */
    public function verify(string $sql, string $input): void
    {
        $context = "Grammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\n{$context}");
        }
        $rendered = null;
        $structure = null;
        try {
            $operation = $this->semantics->analyze($sql);
            if (!$operation instanceof Statement) {
                throw new Error('A generated MySQL statement must publish its concrete statement values.');
            }
            $information = $operation->statementInformation();
            $out = new Output(new Codec($information->profile->grammar, $information->profile->lexical, $information->client));
            $operation->render($out);
            $structure = (new Lexical())->join($out->canonical());
            $rendered = $operation->toString();
            $expected = $this->formatter->format($sql);
            $canonical = $this->formatter->format($structure);
            if ($canonical !== $expected) {
                throw new Error("Compact SQL differs before output-name spelling\nOriginal: {$expected}\nSemantic structure: {$canonical}");
            }
            $actual = $this->formatter->format($rendered);
            if ($actual !== $expected) {
                throw new Error("Compact SQL differs\nOriginal: {$expected}\nReconstructed: {$actual}");
            }
        } catch (Throwable $failure) {
            throw new Error("Semantic round trip failed\n{$context}\nSemantic structure: {$structure}\nRendered: {$rendered}\nError: {$failure->getMessage()}", 0, $failure);
        }
    }
}
