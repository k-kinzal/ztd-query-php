"""Independently replay resolved external MySQL view evidence."""

import run_server_mysql_external_views
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_external_views)
