<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Time;

/**
 * Which dates with zero parts a date and time function takes from its argument.
 *
 * Each function reads its argument in one of these ways (verified on a live 8.4 server):
 * DATE and TIMESTAMP as sql_mode says (Modes); DATE_FORMAT, YEAR, MONTH, DAY and the other parts
 * refuse the zero date as NO_ZERO_DATE says but take a zero month or day (Dated); the functions
 * that count days, such as TO_DAYS, DATEDIFF, WEEK and DAYNAME, refuse both (Refused); LAST_DAY
 * refuses the zero date and a zero month and takes a zero day (Months).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
enum Zeros
{
    case Modes;
    case Dated;
    case Refused;
    case Months;
}
