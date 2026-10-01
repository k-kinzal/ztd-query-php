# Deriver

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/deriver.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/deriver)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-deriver-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Deriver derives PHP values, state, and dependencies from source code without executing application files or their autoloaders. Queries select return values, evaluated expressions, or storage observations. Results retain symbolic inputs, branch conditions, shared references and objects, and exceptional outcomes. Unsupported operations and exhausted analysis budgets leave explicit unresolved dependencies.

## Requirements

- PHP 8.1+ with the JSON and Tokenizer extensions
- A 64-bit PHP runtime
- Source targeting PHP 8.3 semantics; the host PHP version does not change the analysis target

## Installation

```bash
composer require k-kinzal/deriver
```

## Usage

```php
use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;

$session = (new Analyzer())->open(new ProjectInput([
    new SourceFile('app.php', <<<'PHP'
<?php
function userKey(int $id): string
{
    return 'user:' . $id;
}
PHP),
]));

$result = $session->derive(new ReturnQuery('userKey'));
$value = $result->normalOutcomes[0]->values['return'];

// $value retains concatenation with the symbolic parameter id.
echo $result->toJson();
```

The default scope keeps parameters symbolic. Supply an entrypoint to derive the value for a specific invocation:

```php
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Value\Term;

$query = new ReturnQuery('userKey', QueryScope::fromEntrypoints([
    new EntryPoint('userKey', [Term::constant(42)]),
]));
$result = $session->derive($query);

echo $result->normalOutcomes[0]->values['return']->native(); // user:42
```

Inspect the result's assessment, unresolved dependencies, and exceptional outcomes before treating a normal value as exhaustive. A symbolic value can be complete even when its input is unknown.

Queries, models, and result types are described in the [API documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/).

## License

MIT License. See [LICENSE](LICENSE) for details.
