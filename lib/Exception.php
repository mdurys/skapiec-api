<?php

namespace MDurys\SkapiecAPI;

/**
 * Exception thrown by Client::query() method.
 *
 * @author Michał Durys <michal@durys.pl>
 */
class Exception extends \Exception
{
    private $url;

    public function __construct($message = null, $code, \Exception $previous = null, $url = null)
    {
        $this->url = $url;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns URL used to make API call that caused exception.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}
