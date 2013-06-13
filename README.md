skapiec-api
===========

Library for accessing Skąpiec.pl API. See http://api.skapiec.pl for more details.

Installation
------------

Using Composer:

    composer.phar require mdurys/skapiec=dev-master

Usage
-----

Creating API client object:

    $api = new mdurys\SkapiecAPI\Client($username, $password);

The library maps most of the API functions directly to class methods. For
example, API's `beta_listDepartments()` function is called like this:

    $result = $api->beta_listDepartments();

If a function has required arguments they should be given in the same order as
described on http://api.skapiec.pl, for example `beta_getProductInfo()`, which
requires `id` and `category` arguments is called like this:

    $result = $api->beta_getProductInfo($id, $category);

There are a few functions which have two mutually exclusive required parameters,
like `beta_getOffersBestPrice()`, which requires either `component` (i.e. an ID
in our database) or `id_skapiec` (i.e. an ID from Skapiec.pl database). In such
cases the library offers two distinct functions, e.g.

    $result1 = $api->beta_getOffersBestPriceBySkapiecId($skapiecId);
    $result2 = $api->beta_getOffersBestPriceByComponent($componentId);

Optional arguments can be supplied using `setXXX()` methods. For example to list
products from a category with `offset` and `amount` arguments one should call:

    $result = $api
        ->setAmount($amount)
        ->setOffset($offset)
        ->beta_listProducts($categoryId);
