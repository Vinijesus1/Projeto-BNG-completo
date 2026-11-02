<?php

namespace bng\Controllers;

abstract class BaseController
{
    public function view( string $view, $data = [])
    {
        // check if data is array
        if (!is_array($data)) {
            var_dump($data);
            die("Data is not an array");
        }

        // transforms data into variables
        extract($data);

        // includes the file if exists
        $viewFile = __DIR__ . "/../views/$view.php";

        if (file_exists($viewFile)) {
            require_once($viewFile);
        } else {
            die("View não encontrada: " . $view);
        }
    }
}