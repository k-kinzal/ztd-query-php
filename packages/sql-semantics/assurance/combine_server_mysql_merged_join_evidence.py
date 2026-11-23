"""Independently replay every registered MySQL merged join release."""

import run_server_mysql_merged_join
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_merged_join)
