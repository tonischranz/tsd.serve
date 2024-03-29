<?php

namespace tsd\serve;

class StaticController extends Controller
{
    private ViewContext $ctx;
    const 
    MIME_TYPES = ['svg' => 'image/svg+xml', 'css' => 'text/css'];

    function show(array $parts)
    {
        $ext = array_pop($parts);
        $plugin = array_shift($parts);

        $file = App::PLUGINS . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $parts) . '.' . $ext;

        if (!file_exists($file)) throw new NotFoundException;

        if (array_key_exists($ext, StaticController::MIME_TYPES)) return new FileResult($file, StaticController::MIME_TYPES[$ext]);
        
        return new FileResult($file);
    }

    function showFaviconSvg()
    {
        if ($this->ctx->layoutPlugin)
        {
            $file = App::PLUGINS . DIRECTORY_SEPARATOR . $this->ctx->layoutPlugin . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'favicon.svg';
            if (file_exists($file)) return new FileResult($file, 'image/svg+xml');
        }
        $file = 'favicon.svg';
        if (file_exists($file)) return new FileResult($file, 'image/svg+xml');
        else
        {
            $svg = <<< 'EOSVG'
            <?xml version="1.0" encoding="UTF-8" ?>
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="100" height="100">
                <path d="M 83.6,83.6 Q 81.1,86 79.35,87.8 L 77.6,89.6 Q 77.3,89.4 64.4,76.4 51.3,63.3 51.1,63.4 l -0.2,-0.1 q -0.2,0.1 -13.2,13.1 l -13.2,13.2 -6,-6 -6,-6 13.2,-13.2 3.3,-3.3 q 0,0 2.9,-2.95 2.9,-2.95 2.4,-2.45 l 1.95,-1.95 1.4,-1.4 0.85,-0.85 q 0,0 0.2,-0.3 V 51 q 0.1,-0.1 -6.9,-7.2 l -0.1,-0.1 -0.1,-0.1 q -7.3,-7.2 -7.5,-7.3 -0.3,0 -5.6,5.3 l -0.1,0.1 -0.1,0.2 -5.5,5.5 L 6.4,41 0,34.6 5.5,29.1 Q 6.6,28.1 7.35,27.3 8.1,26.5 8.75,25.85 9.4,25.2 9.8,24.75 10.2,24.3 10.5,24 10.8,23.7 10.9,23.55 L 11,23.4 V 23.2 Q 10.9,23 10.4,22.5 l -0.7,-0.7 6,-6 6,-6 0.7,0.7 q 0.7,0.6 0.8,0.5 h 0.2 q 0.1,0.1 5.4,-5.2 L 28.9,5.7 29,5.6 34.6,0 41,6.4 l 6.4,6.4 -5.6,5.5 q -1.7,1.8 -2.95,3.05 -1.25,1.25 -1.9,1.95 L 36.3,24 v 0.2 q 0,0.1 7,7.2 l 0.1,0.1 0.1,0.1 q 1,1 1.9,1.85 0.9,0.85 1.6,1.6 0.7,0.75 1.35,1.35 0.65,0.6 1.1,1.05 0.45,0.45 0.8,0.75 0.35,0.3 0.5,0.45 l 0.15,0.15 h 0.2 q 0.1,0.1 7.2,-7 l 0.1,-0.1 0.1,-0.1 q 7.2,-7.3 7.3,-7.5 -0.3,-0.4 -5.5,-5.7 l -1.75,-1.75 -1.5,-1.5 -1.2,-1.2 q 0,0 -0.8,-0.85 L 54.8,12.8 61.2,6.4 67.5,0 73,5.5 74.75,7.25 76.3,8.8 q 0,0 1.25,1.2 1.25,1.2 0.85,0.75 l 0.3,0.25 0.1,0.1 q 0.3,-0.1 0.8,-0.6 l 0.7,-0.7 6,6 6,6 -0.6,0.6 q -0.6,0.7 -0.6,0.9 0,0.2 3.1,3.4 l 0.1,0.1 0.1,0.1 3.3,3.3 -0.1,1.1 q -0.4,2.6 0,5.3 0.5,3.3 1.9,6.1 l 0.5,0.8 -3.6,3.6 -1.15,1.15 -1,1 -0.8,0.8 q 0,0 -0.55,0.5 l -0.2,0.15 q -0.2,-0.1 -7.3,-7.2 l -1.35,-1.35 -1.25,-1.25 -1.1,-1.1 -0.95,-0.95 -0.8,-0.8 q 0,0 -0.65,-0.6 -0.65,-0.6 -0.55,-0.45 0.1,0.15 -0.4,-0.35 L 78.1,36.4 78,36.3 q -0.2,0 -7.2,7 l -0.1,0.1 -0.1,0.1 q -7.3,7.3 -7.3,7.6 0,0.3 12.9,13.1 l 0.1,0.1 0.1,0.2 13.2,13.1 z" />
            </svg>
            EOSVG;
            return new TextResult($svg, 'image/svg+xml');
        }
    }

    function showStyleCss()
    {
        if ($this->ctx->layoutPlugin)
        {
            $file = App::PLUGINS . DIRECTORY_SEPARATOR . $this->ctx->layoutPlugin . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'style.css';
            if (file_exists($file)) return new FileResult($file, 'text/css');
        }
        $file = 'style.css';
        if (file_exists($file)) return new FileResult($file, 'text/css');
        else
        {
            $css = <<< 'EOCSS'
            html { scrollbar-color: #222 #000; scrollbar-width: thin; background: linear-gradient(90deg, #222 55%, #000); }
            body::-webkit-scrollbar { width: .3em; }
            body::-webkit-scrollbar-track { box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.3); }
            body::-webkit-scrollbar-thumb { background-color: #222; outline: .2em solid #010; }
            body { color:#ddd; background-color:#222; font-family: sans-serif; margin:0; margin-bottom: 1.5em; }
            a, a:visited { text-decoration: none; color:#aaa; }
            a:active, a:hover { text-decoration:#ddd underline; }        
            button {  border: thin solid #888; background-color: #000; background-image: radial-gradient(farthest-corner at -10% -10%, #000, #000, #111, #444); color: #ddd; font-weight: bold; font-size: 2em; border-radius: .5em; padding:.25em 1em; outline:none; }
            button:hover { border: thin solid #888; background-image: radial-gradient(farthest-corner at 110% 110%, #000, #111, #222, #888); }
            button:active { background-image: radial-gradient(farthest-corner at -10% -10%, #000, #000, #111, #444); }
            h1 { font-size: 2.5rem; }
            div.gap { height: 2em; }
            input, input:focus { color:#ddd; background-color:#222; border-style:solid; border-radius: .5em; padding:.25em; font-size: 1.5em; width:100%; box-sizing:border-box; outline:none; text-align:right; padding-right: 1em;}
            input::placeholder { text-align:left;font-size:.8em; }
            input:focus::placeholder {font-size:.6em; }
            input[type=checkbox] {width:auto; margin-right:.7em;}
            div.right {text-align: right;}
            span.error {color:#a00;}
            div {margin-top: .5em;}
            body {padding-bottom: 3.5em }
            body>header { background-color:#111; }
            body>header>nav ul {list-style-type:none; padding-inline-start:0; margin-block-start:0; margin-block-end:0; font-size:4rem;}                    
            body>main>*, body>footer>* { overflow-x:auto; scrollbar-color: #000 #111; scrollbar-width: thin; }
            body>main *::-webkit-scrollbar, body>footer *::-webkit-scrollbar { width : .3em; height: .3em; }
            body>main *::-webkit-scrollbar-track, body>footer *::-webkit-scrollbar-track { box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.3); }
            body>main *::-webkit-scrollbar-thumb, body>footer *::-webkit-scrollbar-thumb { background-color: #000; outline: .2em solid #010; }
            body footer.debug>pre { padding-bottom: .75em; }
            body>footer.sticky { position:fixed; bottom:0; left:0; right:0; padding: .35em; padding-top:.02em; background-color: #0008; }
            @media screen and (min-width: 86rem) { body>header>nav, body>main, body>footer{ width: 80rem; margin:auto; } }
            @media screen and (min-width: 56rem) and (max-width: 86rem){ body>header>nav, body>main, body>footer>{ width: 52rem; margin:auto; } }
            @media screen and (min-width: 38rem) and (max-width: 56rem) { body>header>nav, body>main, body>footer>{ width: 36rem; margin:auto; } }
            @media screen and (min-width: 24rem) and (max-width: 38rem) { body>header>nav, body>main, body>footer>{ margin-left:1rem; margin-right:1rem; } }
            EOCSS;
            return new TextResult($css, 'text/css');
        }
    }

    function showFaviconIco()
    {
        if ($this->ctx->layoutPlugin)
        {
            $file = App::PLUGINS . DIRECTORY_SEPARATOR . $this->ctx->layoutPlugin . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'favicon.ico';
            if (file_exists($file)) return new FileResult($file);
        }
        $file = 'favicon.ico';
        if (!file_exists($file)) throw new NotFoundException;
        return new FileResult($file);
    }
}
