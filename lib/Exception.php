<?php

namespace MDurys\SkapiecAPI;

/**
 * Exception thrown by Client::query() method.
 *
 * @author Michał Durys <michal@durys.pl>
 */
class Exception extends \Exception
{
    /**
     * @var string
     */
    private $url;

    /**
     * Exception constructor.
     *
     * @param string | null     $message
     * @param int               $code
     * @param \Exception | null $previous
     * @param string | null     $url
     */
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
