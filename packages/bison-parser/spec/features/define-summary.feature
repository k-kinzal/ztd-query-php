@manual
Feature: %define Summary
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.7.14   %define Summary                              manual:_0025define-Summary

  A %define assigns a variable. Braces hold a value in the target language,
  a bare keyword selects a finite choice, and a string covers the remaining
  cases.

  @manual:_0025define-Summary
  Scenario: %define with the variable alone
    Given the grammar file:
      """
      %define api.pure
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.pure
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:_0025define-Summary
  Scenario: %define with a keyword value
    Given the grammar file:
      """
      %define api.pure full
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.pure = full
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:_0025define-Summary
  Scenario: %define with a braced value in the target language
    Given the grammar file:
      """
      %define api.prefix {c}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.prefix = {c}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:_0025define-Summary
  Scenario: %define with a string value
    Given the grammar file:
      """
      %define api.location.file "loc.hh"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.location.file = "loc.hh"
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:_0025define-Summary
  Scenario: A Boolean variable accepts true, false, no value, or the empty string
    Given the grammar file:
      """
      %define parse.trace true
      %define parse.assert false
      %define api.token.raw
      %define lr.keep-unreachable-state ""
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define parse.trace = true
      Define parse.assert = false
      Define api.token.raw
      Define lr.keep-unreachable-state = ""
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:_0025define-Summary
  Scenario: %define requires a variable name
    Given the grammar file:
      """
      %define
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:_0025define-Summary
  Scenario Outline: <declaration> is accepted as the manual describes it
    Given the grammar file:
      """
      <declaration>
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      <node>
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

    Examples: Variables of the api group
      | declaration                                       | node                                                     |
      | %define api.filename.type {const std::string}     | Define api.filename.type = {const std::string}           |
      | %define api.header.include {"header.h"}           | Define api.header.include = {"header.h"}                 |
      | %define api.header.include {<header.h>}           | Define api.header.include = {<header.h>}                 |
      | %define api.location.file "loc.hh"                | Define api.location.file = "loc.hh"                      |
      | %define api.location.file none                    | Define api.location.file = none                          |
      | %define api.location.include {"loc.hh"}           | Define api.location.include = {"loc.hh"}                 |
      | %define api.location.include {<loc.hh>}           | Define api.location.include = {<loc.hh>}                 |
      | %define api.location.type {location_t}            | Define api.location.type = {location_t}                  |
      | %define api.namespace {foo::bar}                  | Define api.namespace = {foo::bar}                        |
      | %define api.parser.class {calcxx_parser}          | Define api.parser.class = {calcxx_parser}                |
      | %define api.prefix {c}                            | Define api.prefix = {c}                                  |
      | %define api.pure full                             | Define api.pure = full                                   |
      | %define api.push-pull both                        | Define api.push-pull = both                              |
      | %define api.symbol.prefix {S_}                    | Define api.symbol.prefix = {S_}                          |
      | %define api.token.constructor                     | Define api.token.constructor                             |
      | %define api.token.prefix {TOK_}                   | Define api.token.prefix = {TOK_}                         |
      | %define api.token.raw                             | Define api.token.raw                                     |
      | %define api.value.automove                        | Define api.value.automove                                |
      | %define api.value.type union                      | Define api.value.type = union                            |
      | %define api.value.type variant                    | Define api.value.type = variant                          |
      | %define api.value.type {struct semantic_value}    | Define api.value.type = {struct semantic_value}          |
      | %define api.value.union.name yystype_t            | Define api.value.union.name = yystype_t                  |

    Examples: Variables of the lr and parse groups
      | declaration                                       | node                                                     |
      | %define lr.default-reduction accepting            | Define lr.default-reduction = accepting                  |
      | %define lr.keep-unreachable-state                 | Define lr.keep-unreachable-state                         |
      | %define lr.type ielr                              | Define lr.type = ielr                                    |
      | %define parse.assert                              | Define parse.assert                                      |
      | %define parse.error detailed                      | Define parse.error = detailed                            |
      | %define parse.lac full                            | Define parse.lac = full                                  |
      | %define parse.trace                               | Define parse.trace                                       |

    Examples: Obsolete variables still listed
      | declaration                                       | node                                                     |
      | %define namespace {foo}                           | Define namespace = {foo}                                 |
      | %define parser_class_name {calcxx_parser}         | Define parser_class_name = {calcxx_parser}               |
