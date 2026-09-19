Feature: Writing GLR Parsers
  GNU Bison 3.8.2 manual, chapter "Writing GLR Parsers", sections "Using GLR
  on Unambiguous Grammars", "Using GLR to Resolve Ambiguities" and
  "Controlling a Parse with Arbitrary Predicates".

  Scenario: %glr-parser requests a GLR parser
    Given the grammar file:
      """
      %glr-parser
      %expect-rr 1
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Flag glr-parser
      Expect reduce/reduce 1
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: %dprec after the components gives a rule a dynamic precedence for resolving ambiguities
    Given the grammar file:
      """
      %glr-parser
      %%
      stmt:
        expr ';'  %dprec 1
      | decl      %dprec 2
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      Flag glr-parser
      %%
      Rule stmt
        Alternative
          SymbolItem expr
          SymbolItem ';'
          DprecItem 1
        Alternative
          SymbolItem decl
          DprecItem 2
      """

  Scenario: %dprec requires an integer
    Given the grammar file:
      """
      %glr-parser
      %%
      stmt: expr ';' %dprec;
      """
    When the file is parsed
    Then parsing fails at line 3 column 22

  Scenario: %merge after the components names, in a tag, the function merging the semantic values
    Given the grammar file:
      """
      %glr-parser
      %%
      stmt:
        expr ';'  %merge <stmt_merge>
      | decl      %merge <stmt_merge>
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      Flag glr-parser
      %%
      Rule stmt
        Alternative
          SymbolItem expr
          SymbolItem ';'
          MergeItem <stmt_merge>
        Alternative
          SymbolItem decl
          MergeItem <stmt_merge>
      """

  Scenario: %merge requires a tag
    Given the grammar file:
      """
      %glr-parser
      %%
      stmt: expr ';' %merge stmt_merge;
      """
    When the file is parsed
    Then parsing fails at line 3 column 23

  Scenario: A semantic predicate is written %? followed by a braced expression, where an action could be
    Given the grammar file:
      """
      %glr-parser
      %%
      widget:
        %?{  new_syntax } "widget" id new_args  { $$ = f($3, $4); }
      | %?{ !new_syntax } "widget" id old_args  { $$ = f($3, $4); }
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      Flag glr-parser
      %%
      Rule widget
        Alternative
          Predicate {  new_syntax }
          SymbolItem "widget"
          SymbolItem id
          SymbolItem new_args
          Action { $$ = f($3, $4); }
        Alternative
          Predicate { !new_syntax }
          SymbolItem "widget"
          SymbolItem id
          SymbolItem old_args
          Action { $$ = f($3, $4); }
      """

  Scenario: Predicate actions may not be given labels
    Given the grammar file:
      """
      %glr-parser
      %%
      widget: %?{ new_syntax }[check] "widget";
      """
    When the file is parsed
    Then parsing fails at line 3 column 25
