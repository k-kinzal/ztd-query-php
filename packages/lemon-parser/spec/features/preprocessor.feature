@manual
Feature: The %if directive and its friends
  Source: "The Lemon Parser Generator", doc/lemon.html of SQLite version 3.47.2
  (https://sqlite.org/src/raw/doc/lemon.html?ci=version-3.47.2, published at
  https://sqlite.org/lemon.html). Every scenario is tagged with the section of
  the manual it states, as "manual:" followed by the section number.
  The sections stated in this feature:
    4.4.8  The %if directive and its friends            manual:4.4.8

  The names Lemon takes as -DMACRO options are given to the parser as the
  names defined; a scenario without that step defines none.

  @manual:4.4.8
  Scenario: Text between %ifdef MACRO and its %endif is ignored unless MACRO is defined
    Given the grammar file:
      """
      %ifdef MACRO
      expr ::= ONE.
      %endif
      expr ::= TWO.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item TWO
      """

  @manual:4.4.8
  Scenario: Text between %ifdef MACRO and its %endif is included when MACRO is defined
    Given the grammar file:
      """
      %ifdef MACRO
      expr ::= ONE.
      %endif
      expr ::= TWO.
      """
    And the names defined: MACRO
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      Rule expr
        Item TWO
      """

  @manual:4.4.8
  Scenario: Text between %ifndef MACRO and its %endif is included except when MACRO is defined
    Given the grammar file:
      """
      %ifndef MACRO
      expr ::= ONE.
      %endif
      expr ::= TWO.
      """
    And the names defined: MACRO
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item TWO
      """

  @manual:4.4.8
  Scenario: Text between %ifndef MACRO and its %endif is included when MACRO is not defined
    Given the grammar file:
      """
      %ifndef MACRO
      expr ::= ONE.
      %endif
      expr ::= TWO.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      Rule expr
        Item TWO
      """

  @manual:4.4.8
  Scenario: %if takes names joined with || and &&, negated with !, and grouped with parentheses
    Given the grammar file:
      """
      %if A || B
      expr ::= OR.
      %endif
      %if A && B
      expr ::= AND.
      %endif
      %if !B
      expr ::= NOT.
      %endif
      %if (A || B) && !C
      expr ::= GROUP.
      %endif
      """
    And the names defined: A
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item OR
      Rule expr
        Item NOT
      Rule expr
        Item GROUP
      """

  @manual:4.4.8
  Scenario: Each term of %if is true when the name is defined and false when it is not
    Given the grammar file:
      """
      %if A
      expr ::= ONE.
      %endif
      %if B
      expr ::= TWO.
      %endif
      """
    And the names defined: A, C
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      """

  @manual:4.4.8
  Scenario: %else in between switches to the other text when the condition is false
    Given the grammar file:
      """
      %ifdef MACRO
      expr ::= ONE.
      %else
      expr ::= TWO.
      %endif
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item TWO
      """

  @manual:4.4.8
  Scenario: %else in between drops the other text when the condition is true
    Given the grammar file:
      """
      %ifdef MACRO
      expr ::= ONE.
      %else
      expr ::= TWO.
      %endif
      """
    And the names defined: MACRO
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      """

  @manual:4.4.8
  Scenario: Regions nest, and each %ifdef ends at its own nested %endif
    Given the grammar file:
      """
      %ifdef OUTER
      expr ::= ONE.
      %ifdef INNER
      expr ::= TWO.
      %endif
      expr ::= THREE.
      %endif
      expr ::= FOUR.
      """
    And the names defined: OUTER
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      Rule expr
        Item THREE
      Rule expr
        Item FOUR
      """

  @manual:4.4.8
  Scenario: An excluded region drops its nested regions whole
    Given the grammar file:
      """
      %ifdef OUTER
      expr ::= ONE.
      %ifdef INNER
      expr ::= TWO.
      %endif
      expr ::= THREE.
      %endif
      expr ::= FOUR.
      """
    And the names defined: INNER
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item FOUR
      """

  @manual:4.4.8
  Scenario: These directives must begin at the left margin, so an indented one is read as an unknown declaration
    Given the grammar file:
      """
       %ifdef MACRO
      expr ::= ONE.
      %endif
      """
    When the file is parsed
    Then parsing fails at line 1 column 3

  @manual:4.4.8
  Scenario: No whitespace is allowed between the % and the directive name
    Given the grammar file:
      """
      % ifdef MACRO
      expr ::= ONE.
      %endif
      """
    When the file is parsed
    Then parsing fails at line 1 column 3

  @manual:4.4.8
  Scenario: The directives may stand among declarations and rules alike
    Given the grammar file:
      """
      %token_prefix TK_
      %ifndef OMIT_MINUS
      %left MINUS.
      expr ::= expr MINUS expr.
      %endif
      expr ::= VALUE.
      """
    When the file is parsed
    Then the tree is:
      """
      Directive token_prefix TK_
      PrecedenceDeclaration left MINUS
      Rule expr
        Item expr
        Item MINUS
        Item expr
      Rule expr
        Item VALUE
      """
