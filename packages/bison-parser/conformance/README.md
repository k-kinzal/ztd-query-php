# Conformance with GNU Bison

`php conformance/run.php --bison=PATH DIRECTORY|FILE...` (or `composer conformance -- ...`) compares this package with GNU Bison on every `.y` and `.yy` file found.

For each file:

1. Bison reads it and writes its XML report (`--xml`): rules, symbols with their numbers and types, precedence, and the whole LALR automaton.
2. This package reads it. A file Bison's scanner or parser refuses must raise a `SyntaxException`; a file Bison refuses for its meaning (an undeclared symbol, an unused `%define`) is only read.
3. The tree is printed and Bison reads the reprint under the same file name. The two reports must be identical.

A difference in any of the three is a failure and the exit status is 1, except for the files listed by digest in `known-ties.txt`: Bison numbers symbols by location and breaks ties unstably, so a multi-start grammar whose start symbol is a token may number that token and its switching token either way; symbol numbers are already left out of the comparison, but token numbers follow them.

## Corpus

- `corpus/`: hand-written grammars that use every declaration and every rule form Bison accepts, and under `corpus/reject/` files Bison's scanner or parser refuses.
- `corpus.txt`: real-world grammars fetched by URL with `php conformance/fetch.php DIRECTORY`.
- The CI job adds the examples shipped with Bison 3.8.2 and the grammars materialised from Bison's own test suite (`tests/testsuite -d` with no compiler, which writes every `input.y` and stops).

## Running locally

```sh
composer conformance -- --bison=/usr/local/bin/bison conformance/corpus
php conformance/fetch.php build/conformance/remote && composer conformance -- --bison=bison build/conformance/remote
```

Bison 3.8.2 is the reference; another release may report different automata for the same grammar and is reported in the first line of output.
