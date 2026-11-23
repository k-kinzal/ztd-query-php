"""Independently replay the pinned MySQL session declaration observations."""

import run_server_mysql_session_create_table
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_session_create_table)
