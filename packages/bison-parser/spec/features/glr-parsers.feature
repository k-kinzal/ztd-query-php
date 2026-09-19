@manual
Feature: Writing GLR Parsers
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 1, The Concepts of Bison
    1.5.1    Using GLR on Unambiguous Grammars            manual:Simple-GLR-Parsers
    1.5.2    Using GLR to Resolve Ambiguities             manual:Merging-GLR-Parses
    1.5.4    Controlling a Parse with Arbitrary Predicatesmanual:Semantic-Predicates

  @manual:Simple-GLR-Parsers
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

  @manual:Merging-GLR-Parses
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

  @manual:Merging-GLR-Parses
  Scenario: %dprec requires an integer
    Given the grammar file:
      """
      %glr-parser
      %%
      stmt: expr ';' %dprec;
      """
    When the file is parsed
    Then parsing fails at line 3 column 22

  @manual:Merging-GLR-Parses
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

  @manual:Merging-GLR-Parses
  Scenario: %merge requires a tag
    Given the grammar file:
      """
      %glr-parser
      %%
      stmt: expr ';' %merge stmt_merge;
      """
    When the file is parsed
    Then parsing fails at line 3 column 23

  @manual:Semantic-Predicates
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

  @manual:Semantic-Predicates
  Scenario: Predicate actions may not be given labels
    Given the grammar file:
      """
      %glr-parser
      %%
      widget: %?{ new_syntax }[check] "widget";
      """
    When the file is parsed
    Then parsing fails at line 3 column 25
