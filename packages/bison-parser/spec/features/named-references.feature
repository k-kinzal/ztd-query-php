Feature: Named References
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "Named
  References".

  Explicit names are declared as a bracketed name after a symbol in a rule,
  after the result of a rule, or after the closing brace of a midrule action.
  References to them inside the action code ($name, $[name], @name, @[name])
  are kept verbatim.

  Scenario: Original symbol names may be used as references in the action code
    Given the grammar file:
      """
      %%
      invocation: op '(' args ')'
        { $invocation = new_invocation ($op, $args, @invocation); };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule invocation
        Alternative
          SymbolItem op
          SymbolItem '('
          SymbolItem args
          SymbolItem ')'
          Action { $invocation = new_invocation ($op, $args, @invocation); }
      """

  Scenario: An explicit name is declared as a bracketed name after a symbol appearance
    Given the grammar file:
      """
      %%
      exp[result]: exp[left] '/' exp[right]
        { $result = $left / $right; };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp [result]
        Alternative
          SymbolItem exp [left]
          SymbolItem '/'
          SymbolItem exp [right]
          Action { $result = $left / $right; }
      """

  Scenario: An explicit name may be declared after the closing brace of a midrule action
    Given the grammar file:
      """
      %%
      exp[res]: exp[x] '+' {$left = $x;}[left] exp[right]
        { $res = $left + $right; };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp [res]
        Alternative
          SymbolItem exp [x]
          SymbolItem '+'
          Action {$left = $x;} [left]
          SymbolItem exp [right]
          Action { $res = $left + $right; }
      """

  Scenario: Names containing dots and dashes are referenced with the bracketed syntax in the code
    Given the grammar file:
      """
      %%
      if-stmt: "if" '(' expr ')' "then" then.stmt ';'
        { $[if-stmt] = new_if_stmt ($expr, $[then.stmt]); };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule if-stmt
        Alternative
          SymbolItem "if"
          SymbolItem '('
          SymbolItem expr
          SymbolItem ')'
          SymbolItem "then"
          SymbolItem then.stmt
          SymbolItem ';'
          Action { $[if-stmt] = new_if_stmt ($expr, $[then.stmt]); }
      """

  Scenario: White space is allowed inside the brackets
    Given the grammar file:
      """
      %%
      exp[ result ]: exp[ left ] '+' exp[ right ];
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      %%
      exp[result]: exp[left] '+' exp[right];
      """
