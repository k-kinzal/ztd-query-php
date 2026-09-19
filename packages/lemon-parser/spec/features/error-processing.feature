@manual
Feature: Error Processing
  Source: "The Lemon Parser Generator", doc/lemon.html of SQLite version 3.47.2
  (https://sqlite.org/src/raw/doc/lemon.html?ci=version-3.47.2, published at
  https://sqlite.org/lemon.html). Every scenario is tagged with the section of
  the manual it states, as "manual:" followed by the section number.
  The sections stated in this feature:
    4.4.19 The %syntax_error directive                  manual:4.4.19
    5.0    Error Processing                             manual:5.0

  @manual:4.4.19
  Scenario: %syntax_error gives the C code run when the parser meets a syntax error
    Given the grammar file:
      """
      %syntax_error {
        fprintf(stderr, "syntax error near %s\n", TOKEN.z);
      }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive syntax_error {\n  fprintf(stderr, "syntax error near %s\\n", TOKEN.z);\n}
      """

  @manual:5.0
  Scenario: The special nonterminal error may be used in rules for error recovery
    Given the grammar file:
      """
      stmt ::= expr SEMI.
      stmt ::= error SEMI.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule stmt
        Item expr
        Item SEMI
      Rule stmt
        Item error
        Item SEMI
      """

  @manual:5.0
  Scenario: %syntax_error and %parse_failure may both be given
    Given the grammar file:
      """
      %syntax_error { report(); }
      %parse_failure { giveUp(); }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive syntax_error { report(); }
      Directive parse_failure { giveUp(); }
      """
