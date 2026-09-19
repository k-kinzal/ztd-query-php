Feature: %define Summary
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "%define
  Summary".

  A %define assigns a variable. Braces hold a value in the target language,
  a bare keyword selects a finite choice, and a string covers the remaining
  cases.

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

  Scenario: %define requires a variable name
    Given the grammar file:
      """
      %define
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

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
