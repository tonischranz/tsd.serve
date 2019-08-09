<?php

namespace tsd\serve;

/**
 * @Implementation tsd\serve\ServeViewEngine
 */
abstract class ViewEngine
{
    function render($result, $accept)
    {
        if ($result instanceof tsd\serve\AccessDeniedException) $result = new ErrorResult (403, $result);
        if ($result instanceof tsd\serve\NotFoundException) $result = new ErrorResult (404, $result);
        if ($result instanceof \Exception) $result = new ErrorResult (500, $result->getMessage());
        if (!($result instanceof Result)) $result = new DataResult ($result);

        http_response_code($result->getStatusCode());
        $headers = $result->getHeaders();
        foreach ($headers as $h)
        {
            header($h);
        }

        //todo: better
        if ($accept == 'application/json') $this->renderJson($result);
        if ($accept == 'text/xml') $this->renderXml($result);

        if ($result instanceof ViewResult) 
        {
            $this->renderView($result);
        }
    }

    private function renderJson(Result $result)
    {
        ob_clean();        
        echo json_encode($result->getData());
    }

    private function renderXml(Result $result)
    {
        ob_clean();
        echo $result->getData()->asXML();
    }

    protected abstract function renderView (ViewResult $result);
}

/**
 * @Default
 */
class ServeViewEngine extends ViewEngine
{
    const VIEWS = './views';

    function renderView(ViewResult $result)
    {
        $v = new View (ServeViewEngine::VIEWS.'/'.$result->getView());
        $v->render ($result->getData());        
    }
}