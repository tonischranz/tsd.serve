<?php

namespace egea\Eisbelegung;

use tsd\serve;
use tsd\serve\DB;

class Plan
{
    /** @var DB */
    private $db;

    function __construct(DB $db)
    {
        $this->db = $db;
    }

    function getEvents(int $rink, int $date)
    {
        $date = $date - ($date % 86400);

        return $this->db->select('event', ['*'], [
            'rink' => $rink,
            'date' => ['>=', $date],
            'date' => ['<', $date + 86400]
        ]);
    }
}
