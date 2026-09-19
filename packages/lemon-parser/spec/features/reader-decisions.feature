@reader
Feature: Where this reader is stricter than Lemon
  Source: none but this reader. lemon.c (tool/lemon.c of SQLite 3.47.2) reads
  a file to its end and simply stops, so a rule or a list cut off by the end
  of the file leaves no trace and no message, and its %if evaluation lets an
  unclosed parenthesis pass (eval_preprocessor_boolean, line 2929). This
  reader reports these, because a tree that silently lacks the last rule is
  not a tree of the file. Nothing Lemon rejects is accepted; these are the
  only places where the reader rejects what Lemon lets pass.

  @reader
  Scenario: A rule cut off by the end of the file before its period is an error
    Given the grammar file without a final newline:
      """
      expr ::= VALUE
      """
    When the file is parsed
    Then parsing fails at line 1 column 1
    And the error says:
      """
      Rule "expr" is not terminated by "." before the end of the file.
      """

  @reader
  Scenario: A list cut off by the end of the file before its period is an error
    Given the grammar file without a final newline:
      """
      %left PLUS MINUS
      """
    When the file is parsed
    Then parsing fails at line 1 column 17
    And the error says:
      """
      Declaration is not terminated by "." before the end of the file.
      """

  @reader
  Scenario: An unclosed parenthesis in a %if expression is an error
    Given the grammar file:
      """
      %if (A
      expr ::= ONE.
      %endif
      """
    When the file is parsed
    Then parsing fails at line 1 column 1
