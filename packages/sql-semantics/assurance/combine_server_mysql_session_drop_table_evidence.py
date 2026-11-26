"""Independently replay MySQL persistent and temporary table removal evidence."""

import run_server_mysql_session_drop_table
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_session_drop_table)
