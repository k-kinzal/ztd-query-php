/* Semicolons between alternatives, as Bison's rhses.1 allows. */
%token A B C
%%
start: list ;
list: %empty;|A;;|B C;;;
| list A
;
two: '\\x000000000000000000000000000000000000000000000000000000000000000000002';
