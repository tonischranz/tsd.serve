<?php

use egea\Eisbelegung\Plan;
use tsd\serve;
use tsd\serve\Controller;

class defaultController extends Controller
{
    /** @var Plan */
    private $plan;

    function __construct(Plan $plan)
    {
        $this->plan = $plan;
    }

    function showIndex(?int $date, int $rink = 1)
    {
        $date = $date ?? time();

        var_dump($this);

        $events = $this->plan->getEvents($rink, $date);

        return $this->view([
            'events' => $events,
            'date' => $date
        ]);
    }
}
