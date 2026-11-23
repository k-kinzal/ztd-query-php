"""Independently replay ordinary MySQL WITH observations and faults."""

import run_server_mysql_with_projection
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_with_projection)
