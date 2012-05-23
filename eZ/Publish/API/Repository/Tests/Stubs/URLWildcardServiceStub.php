<?php
/**
 * File containing the URLWildcardServiceStub class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\API\Repository\Tests\Stubs;

use \eZ\Publish\API\Repository\URLWildcardService;
use \eZ\Publish\API\Repository\Values\Content\URLWildcard;
use \eZ\Publish\API\Repository\Values\Content\URLWildcardTranslationResult;
use \eZ\Publish\API\Repository\Tests\Stubs\Exceptions\NotFoundExceptionStub;

/**
 * Url wold service stub implementation.
 *
 * @package eZ\Publish\API\Repository\Tests\Stubs
 */
class URLWildcardServiceStub implements URLWildcardService
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var \eZ\Publish\API\Repository\Values\Content\URLWildcard[]
     */
    private $wildcards = array();

    /**
     * @var \eZ\Publish\API\Repository\Tests\Stubs\RepositoryStub
     */
    private $repository;

    /**
     * Instantiates a new url wildcard stub.
     *
     * @param RepositoryStub $repository
     */
    public function __construct( RepositoryStub $repository )
    {
        $this->repository = $repository;
    }

    /**
     * creates a new url wildcard
     *
     * @param string $sourceUrl
     * @param string $destinationUrl
     * @param boolean $foreward
     *
     * @return \eZ\Publish\API\Repository\Values\Content\UrlWildcard
     * @review The method URLWildcardService::create() should check permissions and throw an AuthorizationException
     * @review The method URLWildcardService::create() should throw an InvalidArgumentException if the $sourceUrl pattern already exists
     * @review The method URLWildcardService::create() should throw a ValidationException if the number of * patterns in $sourceUrl and the number of {\d} placeholders in $destinationUrl doesn't match.
     * @review The method URLWildcardService::create() should throw a ValidationException if the placeholders aren't a valid number sequence({1}/{2}/{3}), starting with 1.
     */
    public function create( $sourceUrl, $destinationUrl, $foreward = false )
    {
        $wildcard = new URLWildcard(
            array(
                'id'  =>  ++$this->id,
                'sourceUrl'  =>  $sourceUrl,
                'destinationUrl'  =>  $destinationUrl,
                'forward'  =>  $foreward
            )
        );

        return ( $this->wildcards[$wildcard->id] = $wildcard );
    }

    /**
     *
     * removes an url wildcard
     *
     * @param \eZ\Publish\API\Repository\Values\Content\UrlWildcard $urlWildcard
     * @review The method URLWildcardService::remove() should check permissions and throw an AuthorizationException.
     * @review The real method type hint for the $urlWildcard parameter of URLWildcardService::remove() is missing.
     */
    public function remove( $urlWildcard )
    {
        unset( $this->wildcards[$urlWildcard->id] );
    }

    /**
     *
     * loads a url wild card
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException if the url wild card was not found
     *
     * @param $id
     *
     * @return \eZ\Publish\API\Repository\Values\Content\UrlWildcard
     * @review The type hint for the $id parameter of URLWildcardService::remove() is missing.
     */
    public function load( $id )
    {
        if ( isset( $this->wildcards[$id] ) )
        {
            return $this->wildcards[$id];
        }
        throw new NotFoundExceptionStub( 'What error code should be used?' );
    }

    /**
     * loads all url wild card (paged)
     *
     * @param $offset
     * @param $limit
     *
     * @return \eZ\Publish\API\Repository\Values\Content\UrlWildcard[]
     * @review The type hint for the $offset parameter of URLWildcardService::loadAll() is missing
     * @review The type hint for the $limit parameter of URLWildcardService::loadAll() is missing
     */
    public function loadAll( $offset = 0, $limit = -1 )
    {
        return array_slice( $this->wildcards, $offset, -1 === $limit ? PHP_INT_MAX : $limit );
    }

    /**
     * translates an url to an existing uri resource or url alias based on the source/destination patterns of the url wildcard.
     * this method runs also configured url translations and filter
     *
     * @param $url
     *
     * @return mixed either an URLAlias or a URLWildcardTranslationResult
     * @review The type hint for the $url parameter of URLWildcardService::translate() is missing
     * @review Wouldn't it be better to translate a matching URLAlias into an URLWildcardTranslationResult, so that the behavior and usage of this method is unified for the using application.
     * @review The URLWildcardService::translate() method should throw a NotFoundException when the given $url doesn't match on any wildcard or alias. Or it should at least specify a return value for this case.
     */
    public function translate( $url )
    {
        uasort(
            $this->wildcards,
            function( URLWildcard $w1, URLWildcard $w2 )
            {
                return strlen( $w2->sourceUrl ) - strlen( $w1->sourceUrl );
            }
        );

        foreach ( $this->wildcards as $wildcard )
        {
            if ( $uri = $this->match( $url, $wildcard ) )
            {
                return new URLWildcardTranslationResult(
                    array(
                        'uri'  =>  $uri,
                        'forward'  =>  $wildcard->forward
                    )
                );
            }
        }
        return $this->repository->getURLAliasService()->lookUp( $url );
    }

    /**
     * Tests if the given url matches against the given url wildcard.
     *
     * if the wildcard matches on the given url this method will return a ready
     * to use destination url, otherwise this method will return <b>NULL</b>.
     *
     * @param string $url
     * @param \eZ\Publish\API\Repository\Values\Content\URLWildcard $wildcard
     * @return string|null
     */
    private function match( $url, URLWildcard $wildcard )
    {
        if ( preg_match( $this->compile( $wildcard->sourceUrl ), $url, $match ) )
        {
            return $this->substitute( $wildcard->destinationUrl, $match );
        }
        return null;
    }

    /**
     * Compiles the given url pattern into a regular expression.
     *
     * @param string $sourceUrl
     * @return string
     */
    private function compile( $sourceUrl )
    {
        return '(^' . str_replace( '\\*', '(.*)', preg_quote( $sourceUrl ) ) . '$)U';
    }

    /**
     * Substitutes all placesholder ({\d}) in the given <b>$destinationUrl</b> with
     * the values from the given <b>$values</b> array.
     *
     * @param string $destinationUrl
     * @param array $values
     * @return string
     */
    private function substitute( $destinationUrl, array $values )
    {
        for ( $i = 1, $c = count( $values ); $i < $c; ++$i )
        {
            $destinationUrl = str_replace( "{{$i}}", $values[$i], $destinationUrl );
        }
        return $destinationUrl;
    }
}