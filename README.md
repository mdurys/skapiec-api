skapiec-api [![Build Status](https://travis-ci.org/mdurys/skapiec-api.svg?branch=master)](https://travis-ci.org/mdurys/skapiec-api)
===========

Library for accessing Skąpiec.pl API. See http://api.skapiec.pl for more details.

Installation
------------

Using Composer:

    composer.phar require mdurys/skapiec-api=dev-master

Usage
-----

Creating API client object:

    $api = new MDurys\SkapiecAPI\Client($username, $password);

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

With majority of functions that return information about a product or a category
one can select which fields to populate. This can be done using three methods:
- `onlyField()` - Only indicated fields will be included in the result.
- `includeField()` - Adds indicated fields to the ones returned by default.
- `excludeField()` - Excludes indicated fields from the ones returned by default.
Each of these methods accepts variable number of arguments.

Example:

    $result = $api
        ->onlyField('name', 'vendor', 'model')
        ->setFromTime(time() - 86400)
        ->beta_listNewProducts($categoryId);

Skapiec.pl imposes limits on number of API calls within certain time. To assure
that limits are not exceeded one can use `setQueryDelay()` method. For example,
to wait a quater of a second between subsequent API calls use this code:

    $api->setQueryDelay(0.25);

Bugs
----

`beta_getProductMostPopular()` doesn't accept 0 as a valid offset. It's a
problem on the API side. It's already reported to Skapiec.pl and hopefully it'll
be fixed sooner than later.

`beta_getDealerInfo()` can return empty result for valid shop IDs. Apparently
this is ok with Skapiec, because shop information is maintained by shop iteself,
not Skapiec and some shops do not provide that information.
