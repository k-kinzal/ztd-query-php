# Deriver

Deriver derives PHP values and state from captured source code. It keeps symbolic inputs, partial structures, branch correlations, reference cells, object identities, and exceptional outcomes in the result. Application files are read as data; Deriver does not include them or run their autoloaders.

## Requirements

- PHP 8.1 or later, with JSON and Tokenizer.
- A 64-bit PHP runtime.
- Source targeting PHP 8.3 semantics. The host PHP version does not select the analysis target.

## Installation

```sh
composer require k-kinzal/deriver
```

## Derive a return value

```php
use Deriver\Analyzer;
use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\SourceFile;
use Deriver\Api\Query\ReturnQuery;

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

A symbolic parameter is a valid result. A missing source body, unsupported operation, or exhausted budget has a separate frontier explaining the unresolved dependency. Inspect the assessment and exceptional outcomes before treating a normal value as exhaustive.

## Supply an entry context

```php
use Deriver\Api\Project\EntryPoint;
use Deriver\Api\Query\QueryScope;
use Deriver\Value\Term;

$query = new ReturnQuery('userKey', QueryScope::fromEntrypoints([
    new EntryPoint('userKey', [Term::constant(42)]),
]));
$result = $session->derive($query);

assert($result->normalOutcomes[0]->values['return']->native() === 'user:42');
```

The default scope represents arbitrary valid inputs. A parameter default is evaluated when an explicit invocation omits that argument; it does not replace every symbolic input.

## Observe evaluated arguments and state

`callsTo()` returns source references to already evaluated argument registers and to the invocation point. Use `TupleQuery` to keep argument combinations correlated, or `StateQuery` to inspect a local variable or a projection of its object state. Reusing these references does not execute the argument expressions again.

```php
use Deriver\Api\Query\TupleQuery;

$sites = $session->callsTo('observe');
// For a source project containing observe($name, $id):
// $result = $session->derive(new TupleQuery(
//     $sites[0]->beforeInvocation(),
//     ['name' => $sites[0]->arguments[0], 'id' => $sites[0]->arguments[1]],
// ));
```

## Documentation

- [Queries and result interpretation](docs/api.md)
- [Semantic contract](docs/design.md)
- [Capability manifest](docs/capabilities.md)
- [Acceptance requirements and verification](docs/acceptance.md)
- [PHP language semantics](docs/language.md)
- [Writing call models and providers](docs/models.md)
- [Runnable examples](examples/README.md)
- [JSON values, metadata, and confidentiality](docs/json.md)
- [Benchmark corpus and measurements](bench/README.md)

## Development checks

The package uses the same php-ai-toolkit checks as the other packages in this repository.

```sh
composer install
composer lint
composer test
composer test:differential
composer fuzz:smoke
composer fuzz:semantic
```

Differential tests run generated fixtures in a separate PHP 8.3 process. Set `DERIVER_PHP83_BINARY` when PHP 8.3 is not the current executable. The analyzer itself never executes these fixtures. Fuzz inputs are also treated exclusively as source data.

## License

MIT. See [LICENSE](LICENSE).
