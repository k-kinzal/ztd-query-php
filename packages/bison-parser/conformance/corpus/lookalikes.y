/* Identifiers that look like directives, numbers and punctuation. */
%token IF_SYM ELSE-SYM a.b.c _under 'a'
%token X0 X1.5 X-Y
%%
if_stmt.opt : IF_SYM ELSE-SYM a.b.c _under | %empty ;
x: X0 X1.5 X-Y 'a' ;
y : x, x ;
#line 100 "ignored.y"
z:
  y
  ;
