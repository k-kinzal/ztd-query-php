"""Replay the MySQL text literal observations and fault witnesses."""

import run_server_mysql_text_literal
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_text_literal)
