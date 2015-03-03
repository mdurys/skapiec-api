<?php

namespace MDurys\SkapiecAPI;

/**
 * Exception thrown by Client::query() method.
 *
 * @author Michał Durys <michal@durys.pl>
 * @package SkapiecAPI
 */
class Exception extends \Exception
{
    private $url;

    /**
     * Set URL used to make API call that caused exception.
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
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
