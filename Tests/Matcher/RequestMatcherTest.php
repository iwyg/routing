<?php

/*
 * This File is part of the Lucid\Module\Routing\Tests\Matcher package
 *
 * (c) iwyg <mail@thomas-appel.com>
 *
 * For full copyright and license information, please refer to the LICENSE file
 * that was distributed with this package.
 */

namespace Lucid\Module\Routing\Tests\Matcher;

use Lucid\Module\Routing\Route;
use Lucid\Module\Routing\RouteCollection;
use Lucid\Module\Routing\Http\RequestContext;
use Lucid\Module\Routing\Matcher\RequestMatcher;
use Lucid\Module\Routing\Matcher\RequestMatcherInterface;
use Lucid\Module\Routing\Cache\CachedCollectionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class RequestMatcherTest
 *
 * @package Lucid\Module\Routing\Tests\Matcher
 * @version $Id$
 * @author iwyg <mail@thomas-appel.com>
 */
class RequestMatcherTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function itIsExpectedThat()
    {
        $m = new RequestMatcher($routes = new RouteCollection);

        $req = Request::create('/user/2/12');

        $context = RequestContext::fromRequest($req);

        foreach (range(1, 20) as $r) {
            $routes->add('route_'.$r, $route = new Route('/user/'.$r.'/{id?}', 'action_'.$r));
        }

        list ($match, $context) = $m->matchRequest($context);

        $this->assertSame(RequestMatcherInterface::MATCH, $match);
        $this->assertInstanceof('Lucid\Module\Routing\Matcher\MatchContextInterface', $context);
    }

    /** @test */
    public function faultyUrlsShouldntMatch()
    {
        $m = new RequestMatcher($routes = new RouteCollection);

        $context = new RequestContext('/unknown/uri', 'GET');

        $routes->add('route', $route = new Route('/', 'action'));

        list ($match, $context) = $m->matchRequest($context);

        $this->assertSame(RequestMatcherInterface::NOMATCH, $match);
        $this->assertInstanceof('Lucid\Module\Routing\Matcher\MatchContextInterface', $context);
    }
}