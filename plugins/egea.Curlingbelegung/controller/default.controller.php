<?php

use egea\Eisbelegung\Plan;
use tsd\serve\Controller;

class defaultController extends Controller
{
    /** @var Plan */
    private $plan;

    function __construct(Plan $plan)
    {
        $this->plan = $plan;
    }

    function showIndex()
    {
        $date = time();

        var_dump($this);

        $events = $this->plan->getEvents(5, $date);

        return $this->view([
            'events' => $events,
            'date' => $date
        ]);
    }
}
