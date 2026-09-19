Feature: %code Summary
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "%code
  Summary".

  Scenario: The unqualified form inserts code at the default location
    Given the grammar file:
      """
      %code {
        static void print_token (yytoken_kind_t token, YYSTYPE val);
      }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code {\n  static void print_token (yytoken_kind_t token, YYSTYPE val);\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario Outline: The qualified form names the purpose of the code with <qualifier>
    Given the grammar file:
      """
      %code <qualifier> { <code> }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code <qualifier> { <code> }
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

    Examples:
      | qualifier | code                       |
      | requires  | #include "ptypes.h"        |
      | provides  | void trace_token (void);   |
      | top       | #define _GNU_SOURCE        |
      | imports   | import java.util.List;     |

  Scenario: Several occurrences of %code with the same qualifier are kept in the order they appear
    Given the grammar file:
      """
      %code requires { #include "type1.h" }
      %code requires { #include "type2.h" }
      %code { static int a; }
      %code { static int b; }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code requires { #include "type1.h" }
      Code requires { #include "type2.h" }
      Code { static int a; }
      Code { static int b; }
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: %code requires braced code
    Given the grammar file:
      """
      %code requires
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1
