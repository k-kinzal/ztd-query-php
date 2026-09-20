@manual
Feature: Named References
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.6      Named References                             manual:Named-References

  Explicit names are declared as a bracketed name after a symbol in a rule,
  after the result of a rule, or after the closing brace of a midrule action.
  References to them inside the action code ($name, $[name], @name, @[name])
  are kept verbatim.

  @manual:Named-References
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

  @manual:Named-References
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

  @manual:Named-References
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

  @manual:Named-References
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
