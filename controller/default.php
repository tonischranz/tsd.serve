<?php

use tsd\serve\Controller;

new class extends Controller {

    function showIndex()
    {
        return $this->view();
    }

    function show(array $words) {

        return $this->view(['words'=>$words], view:'404');
    }

};