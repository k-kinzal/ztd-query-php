<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlCatalog\Facade\Analyzer;

/**
 * Feeds arbitrary bytes to the analyzer as if they were a source file.
 *
 * The analyzer is pointed at whatever a repository happens to contain, so it has
 * to survive anything: a file that is not PHP, a file that is half PHP, a file
 * whose statements nest deeper than anyone would write. Nothing it is given may
 * make it throw; a file it cannot read is reported, not raised.
 */
final class AnalyzeTarget
{
    private readonly Analyzer $analyzer;

    /**
     * Builds the target over an analyzer with the built-in extensions.
     */
    public function __construct()
    {
        $this->analyzer = new Analyzer();
    }

    /**
     * Analyzes the input and checks that the catalog it produced is coherent.
     *
     * @throws Error When the catalog contradicts itself
     */
    public function __invoke(string $input): void
    {
        $catalog = $this->analyzer->analyzeSource(['fuzz.php' => '<?php ' . $input]);

        foreach ($catalog as $entry) {
            if ($entry->id === '' || $entry->site->file !== 'fuzz.php') {
                throw new Error('Catalogued a statement without an identity; input=' . bin2hex($input));
            }
            if ($entry->isExact() && str_contains($entry->sql(), '{$}')) {
                throw new Error('A resolved statement still shows a gap; input=' . bin2hex($input));
            }
        }

        if ($catalog->count() > 0 && $catalog->problems() !== []) {
            throw new Error('Reported both statements and a parse failure; input=' . bin2hex($input));
        }
    }
}
