<?php

use egea\Eisbelegung\Plan;
use tsd\serve\Controller;

class defaultController extends Controller
{
    private Plan $plan;

    function showIndex()
    {
        $date = time();

        try
        {
            $events = $this->plan->getEvents(5, $date);
           
            return $this->view([
            'events' => $events,
            'date' => $date
        ]);
        }
        catch(\Exception $e)
        {
            var_dump($this->plan);
            throw $e;
        }
    }
}
