"""Independently replay native MySQL session table binding observations."""

import run_server_mysql_session_table_sources
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_session_table_sources)
