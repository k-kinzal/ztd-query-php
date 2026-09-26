<?php

declare(strict_types=1);

namespace Tests\Fake;

/**
 * Source documents in every built-in format, quoted by the tests.
 */
final class SourceDocuments
{
    /**
     * Three rule paragraphs in the main scope and one paragraph outside it.
     */
    public const HTML = '<!DOCTYPE html>' . "\n" . '<html><body><main><p id="a">Names shall start with a letter.</p><p id="b">Names may contain digits.</p><p id="c">The generator shall produce C code.</p></main><aside><p id="outside">Outside scope.</p></aside></body></html>' . "\n";

    /**
     * Three rule texts, two of them equal, and a value outside the rules.
     */
    public const JSON = '{"rules":[{"text":"First rule."},{"text":"First rule."},{"text":"Third rule."}],"other":"Outside scope."}' . "\n";

    /**
     * A heading and two rule paragraphs, one with emphasis.
     */
    public const MARKDOWN = "# Rules\n\nFirst **rule**.\n\nSecond rule.\n";

    /**
     * An RFC section with two rule paragraphs.
     */
    public const XML = '<?xml version="1.0"?><rfc><section anchor="rules"><t>First rule.</t><t>Second rule.</t></section></rfc>' . "\n";
}
