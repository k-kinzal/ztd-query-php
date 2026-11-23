"""Independently replay native MySQL view declaration effects."""

import run_server_mysql_create_view
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_view)
