<?php

function smarty_function_dispatch($params, $template)
{
    $saved_REQUEST = $_REQUEST;
    $_REQUEST = $params;
    $str = dispatch($params["c"], $params["a"]);
    $_REQUEST = $saved_REQUEST;
    return $str;
}

?>