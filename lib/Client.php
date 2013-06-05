<?php

namespace mdurys\SkapiecAPI;

/**
 * Client for accessing skapiec.pl API.
 *
 * @author Michał Durys <michal@durys.pl>
 * @package SkapiecAPI
 *
 * @method mixed meta_whoAmI()
 * @method mixed meta_availableServices()
 * @method mixed beta_listProducts(int $category)
 */
class Client
{
    /**
     * Domain of the API host.
     */
    const API_HOST = 'api.skapiec.pl';

    /**
     * Image size IDs for beta_getProductPhoto()
     */
    const PHOTO_SIZE_ALL = 0;
    const PHOTO_SIZE_XSMALL = 1;
    const PHOTO_SIZE_SMALL = 2;
    const PHOTO_SIZE_NORMAL = 3;

    /**
     * Maximal number of records requested in one API call.
     */
    const MAX_AMOUNT = 20;

    protected $outputFormat = 'json';

    /**
     * @var resource CURL handle.
     */
    protected $curlHandle;

    /**
     * @var int Timeout for API calls (seconds).
     */
    protected $timeout = 10;

    /**
     * @var array Query parameters.
     */
    protected $queryParams = array();

    /**
     * @var int Stores URL used to make last API call.
     */
    protected $lastUrl;

    /**
     * @var int Stores HTTP status code returned by last API call.
     */
    protected $lastCode;

    /**
     * @var int Stores raw result returned by last API call.
     */
    protected $lastResult;

    /**
     * @var int Last time (with microseconds) an API call was executed.
     */
    protected $lastMicrotime;

    /**
     * @var float Delay in seconds between API calls.
     */
    protected $queryDelay = 0.0;

    /**
     * @var array Known API methods and their required arguments.
     *
     * Some API methods have mutually exclusive required parameters, e.g.
     * beta_getOffersBestPrice() requires either component id or skapiec id.
     * Such methods are not given any required parameters in the array below.
     * Instead wrapper methods are created, e.g.
     * beta_getOffersBestPriceBySkapiecId($skapiecId) and
     * beta_getOffersBestPriceByComponent($component).
     */
    private static $apiMethods = array(
        'beta_addExpOpinion' => array('component_id_array', 'title', 'url', 'description'),
        'beta_addOffer' => array('component', 'id_skapiec', 'url'),
        'beta_addOpinion' => array('component', 'ocena', 'description', 'category'),
        'beta_addPriceAlert' => array(),
        'beta_addToFavorites' => array('id'),
        'beta_filterProducts' => array('filter', 'category'),
        'beta_getCategoryInfo' => array('id'),
        'beta_getDealerCategories' => array('id'),
        'beta_getDealerInfo' => array('id'),
        'beta_getDealerOffers' => array('id'),
        'beta_getDepartmentInfo' => array('id'),
        'beta_getFilterOptions' => array('filter'),
        'beta_getFilters' => array('category'),
        'beta_getOffersBestPrice' => array(),
        'beta_getOpinionsBestValue' => array(),
        'beta_getOpinionsLatest' => array(),
        'beta_getProductInfo' => array('id', 'category'),
        'beta_getProductMostPopular' => array(),
        'beta_getProductPhoto' => array('id', 'category'),
        'beta_listCategories' => array('department'),
        'beta_listDealerProducts' => array('id'),
        'beta_listDepartments' => array(),
        'beta_listNewProducts' => array(),
        'beta_listProducers' => array('category'),
        'beta_listProducersProducts' => array('producer', 'sort', 'amount'),
        'beta_listProducts' => array('category'),
        'beta_multiSearch' => array('json'),
        'beta_rebindOffer' => array('component', 'id_skapiec'),
        'beta_searchOffers' => array('q'),
        'beta_searchOffersFilters' => array('q'),
        'meta_availableServices' => array(),
        'meta_whoAmI' => array(),
        );

    /**
     * Create new Client object.
     *
     * @param string $apiUser
     * @param string $apiPassword
     */
    public function __construct($apiUser, $apiPassword)
    {
        $this->curlHandle = curl_init();
        curl_setopt_array($this->curlHandle, array(
            CURLOPT_USERAGENT => 'SkapiecApiClient.php',
            CURLOPT_USERPWD => $apiUser . ':' . $apiPassword,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout));
    }

    /**
     * Destroy object and free allocated resources.
     */
    public function __destruct()
    {
        if ($this->curlHandle)
        {
            curl_close($this->curlHandle);
        }
    }

    /**
     * Handle virtual API methods: meta_*, beta_* and set*.
     *
     * @param string $name Name of called method.
     * @param array $arguments Method arguments.
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // handle setXXX methods which set query parameters
        if (strncmp('set', $name, 3) == 0)
        {
            $this->queryParams[self::camelcaseToUnderscore(substr($name, 3))] = current($arguments);
            return $this;
        }
        // handle API methods
        else if (in_array($name, array_keys(self::$apiMethods)))
        {
            // check if this method has any required parameters
            if (!empty(self::$apiMethods[$name]))
            {
                // make sure that number of parameters is correct
                if (count(self::$apiMethods[$name]) <> count($arguments))
                {
                    throw new \BadMethodCallException(get_class($this) . '::' . $name . ' requires ' . count(self::$apiMethods[$name]) . ' argument(s)');
                }
                // add method parameters to query
                $this->queryParams += array_combine(self::$apiMethods[$name], $arguments);
            }

            // build query URL
            $url = 'http://' . self::API_HOST . '/' . $name . '.' . $this->outputFormat;
            if (!empty($this->queryParams))
            {
                $url .= '?' . http_build_query($this->queryParams);
                $this->queryParams = array();
            }

            // at this point we're ready to make a call
            return $this->query($url);
        }

        throw new \BadMethodCallException('Tried to call unknown method ' . get_class($this) . '::' . $name);
    }

    /**
     * Execute Skapiec API query and return its result.
     *
     * @param string $url
     * @return mixed
     * @throws \RuntimeException
     */
    public function query($url)
    {
        // check if we need to pause execution
        if ($this->queryDelay && $this->lastMicrotime)
        {
            $timeElapsed = microtime(true) - $this->lastMicrotime;
            if ($timeElapsed < $this->queryDelay)
            {
                usleep(intval(($this->queryDelay - $timeElapsed) * 1000000));
            }
        }

        // make query
        curl_setopt($this->curlHandle, CURLOPT_URL, $url);
        $this->lastUrl = $url;
        $this->lastResult = curl_exec($this->curlHandle);
        $this->lastMicrotime = microtime(true);
        $this->lastCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
        if ($this->lastCode != 200)
        {
            throw new \RuntimeException($this->lastResult, $this->lastCode);
        }

        switch ($this->outputFormat)
        {
            case 'json':
                return json_decode($this->lastResult, true);
//                return json_decode($this->lastResult, false);
            case 'xml':
                return simplexml_load_string($this->lastResult);
        }
    }

    /**
     * Get best offers for given product.
     *
     * Wrapper for beta_getOffersBestPrice().
     *
     * @param integer|array $idSkapiec
     * @return mixed
     */
    public function beta_getOffersBestPriceBySkapiecId($idSkapiec)
    {
        $this->queryParams['id_skapiec'] = $idSkapiec;
        return $this->__call('beta_getOffersBestPrice', array());
    }

    /**
     * Get best offers for given product.
     *
     * Wrapper for beta_getOffersBestPrice().
     *
     * @param integer|array $component
     * @return mixed
     */
    public function beta_getOffersBestPriceByComponent($component)
    {
        $this->queryParams['component'] = $component;
        return $this->__call('beta_getOffersBestPrice', array());
    }

    /**
     * Get best user reviews for given product.
     *
     * Wrapper for beta_getOpinionsBestValue().
     *
     * @param integer|array $idSkapiec
     * @return mixed
     */
    public function beta_getOpinionsBestValueBySkapiecId($idSkapiec)
    {
        $this->queryParams['id_skapiec'] = $idSkapiec;
        return $this->__call('beta_getOpinionsBestValue', array());
    }

    /**
     * Get best user reviews for given product.
     *
     * Wrapper for beta_getOpinionsBestValue().
     *
     * @param integer|array $component
     * @return mixed
     */
    public function beta_getOpinionsBestValueByComponent($component)
    {
        $this->queryParams['component'] = $component;
        return $this->__call('beta_getOpinionsBestValue', array());
    }

    /**
     * Get latest user reviews for given product.
     *
     * Wrapper for beta_getOpinionsLatest().
     *
     * @param integer|array $idSkapiec
     * @return mixed
     */
    public function beta_getOpinionsLatestBySkapiecId($idSkapiec)
    {
        $this->queryParams['id_skapiec'] = $idSkapiec;
        return $this->__call('beta_getOpinionsLatest', array());
    }

    /**
     * Get latest user reviews for given product.
     *
     * Wrapper for beta_getOpinionsLatest().
     *
     * @param integer|array $component
     * @return mixed
     */
    public function beta_getOpinionsLatestByComponent($component)
    {
        $this->queryParams['component'] = $component;
        return $this->__call('beta_getOpinionsLatest', array());
    }

    /**
     * List most popular products from given department.
     *
     * Wrapper for beta_getProductMostPopular().
     *
     * @param integer $department
     * @return mixed
     */
    public function beta_getProductMostPopularByDepartment($department)
    {
        $this->queryParams['department'] = $department;
        return $this->__call('beta_getProductMostPopular', array());
    }

    /**
     * List most popular products from given category.
     *
     * Wrapper for beta_getProductMostPopular().
     *
     * @param integer $category
     * @return mixed
     */
    public function beta_getProductMostPopularByCategory($category)
    {
        $this->queryParams['category'] = $category;
        return $this->__call('beta_getProductMostPopular', array());
    }

    /**
     * Set which data fields are to be returned by API call.
     *
     * The method accepts variable number of arguments.
     *
     * @param string $name,... Field name.
     * @return \mdurys\SkapiecAPI\Client
     */
    public function onlyField()
    {
        $this->queryParams['onlyField'] = implode(',', func_get_args());
        return $this;
    }

    /**
     * Add data fields to the default ones that are to be returned by API call.
     *
     * The method accepts variable number of arguments.
     *
     * @param string $name,... Field name.
     * @return \mdurys\SkapiecAPI\Client
     */
    public function includeField()
    {
        $this->queryParams['includeField'] = implode(',', func_get_args());
        return $this;
    }

    /**
     * Remove data fields from the default ones that are to be returned by API call.
     *
     * The method accepts variable number of arguments.
     *
     * @param string $name,... Field name.
     * @return \mdurys\SkapiecAPI\Client
     */
    public function excludeField()
    {
        $this->queryParams['excludeField'] = implode(',', func_get_args());
        return $this;
    }

    /**
     * Returns URL used to make last API call.
     *
     * @return string URL used to make last API call.
     */
    public function getLastUrl()
    {
        return $this->lastUrl;
    }

    /**
     * Returns HTTP status code returned by last API call.
     *
     * @return int HTTP status code returned by last API call.
     */
    public function getLastCode()
    {
        return $this->lastCode;
    }

    /**
     * Returns raw reponse from last API call.
     *
     * @return string Response
     */
    public function getLastResult()
    {
        return $this->lastResult;
    }

    /**
     * Set required delay betweeen subsequent API calls. Setting delay to 0
     * disables checking of delay.
     *
     * @param integer $seconds Delay in seconds.
     */
    public function setQueryDelay($seconds)
    {
        $this->queryDelay = $seconds;
        return $this;
    }

    /**
     * Convert string in camelCase to underscore_notation.
     *
     * @param string $string
     * @return string
     */
    protected static function camelcaseToUnderscore($string)
    {
        return strtolower(preg_replace('|(?<=\\w)(?=[A-Z])|', '_$1', $string));
    }
}
