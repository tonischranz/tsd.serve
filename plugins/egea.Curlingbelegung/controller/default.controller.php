<?php

use egea\Eisbelegung\Plan;
use tsd\serve\Controller;

class defaultController extends Controller
{
    private Plan $plan;

    function showIndex()
    {
        $date = time();

        $this->plan->dumpDBConfig();
        //var_dump($this->plan);

        $events = $this->plan->getEvents(5, $date);

        return $this->view([
            'events' => $events,
            'date' => $date
        ]);
    }
}
