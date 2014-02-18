<?php

namespace Yam\Migrations;

class OutputWriter
{
    private $closure;

    public function __construct(\Closure $closure = null)
    {
        if ($closure === null) {
            $closure = function($message) {};
        }
        $this->closure = $closure;
    }

    /**
     * Write output using the configured closure.
     *
     * @param string $message The message to write.
     */
    public function write($message)
    {
        $closure = $this->closure;
        $closure($message);
    }
}
