// Code blocks with everything that could be mistaken for a brace.
%include {
  /* a } in a comment */
  // a } in a line comment
  static const char *s = "a } in a string \" with escapes \\";
  static char c = '}';
  static char q = '\'';
  int nested(void) { if (1) { return 0; } return 1; }
}
%token A B.
start ::= a.
a ::= A. { if (x) { y("}"); } /* } */ // }
}
a ::= B. {
  // a rule action spanning lines
  z('}');
}
a ::= a A. [B] { after_precedence(); }
