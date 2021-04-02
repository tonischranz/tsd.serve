<?php

namespace tsd\serve;

interface IViewResult
{
    function view(): string;
    function plugin(): string;
    function data();
}