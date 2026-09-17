# Fuzzing bison-parser

`composer fuzz:parse` and `composer fuzz:roundtrip` run one PHP-Fuzzer target each.
Both feed arbitrary text to the parser, guided by a dictionary of Bison tokens.

- `fuzz_parse.php` checks the parser's promise on any input: it yields a tree or raises a
  `SyntaxException`, and nothing else escapes.
- `fuzz_roundtrip.php` checks the tree's promise: a text that parses prints to a grammar that
  reads back to the same printed text.

A finding is saved as `crash-<hash>.txt` and replayed with the same target:

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_roundtrip.php crash-<hash>.txt
```

The corpus directories are ignored by git.
