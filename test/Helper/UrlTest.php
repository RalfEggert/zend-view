<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\View\Helper;

use Zend\View\Helper\Url as UrlHelper;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\Router\SimpleRouteStack as Router;
use Zend\Router\RouteMatch as NextGenRouteMatch;
use Zend\Router\SimpleRouteStack as NextGenRouter;

/**
 * Zend\View\Helper\Url Test
 *
 * Tests formText helper, including some common functionality of all form helpers
 *
 * @group      Zend_View
 * @group      Zend_View_Helper
 */
class UrlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var UrlHelper
     */
    private $url;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $router = new Router();
        $router->addRoute('home', [
            'type' => 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route' => '/',
            ]
        ]);
        $router->addRoute('default', [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route' => '/:controller[/:action]',
                ]
        ]);
        $this->router = $router;

        $this->url = new UrlHelper;
        $this->url->setRouter($router);
    }

    public function testHelperHasHardDependencyWithRouter()
    {
        $this->setExpectedException('Zend\View\Exception\RuntimeException', 'No RouteStackInterface instance provided');
        $url = new UrlHelper;
        $url('home');
    }

    public function testHomeRoute()
    {
        $url = $this->url->__invoke('home');
        $this->assertEquals('/', $url);
    }

    public function testModuleRoute()
    {
        $url = $this->url->__invoke('default', ['controller' => 'ctrl', 'action' => 'act']);
        $this->assertEquals('/ctrl/act', $url);
    }

    public function testModel()
    {
        $it = new \ArrayIterator(['controller' => 'ctrl', 'action' => 'act']);

        $url = $this->url->__invoke('default', $it);
        $this->assertEquals('/ctrl/act', $url);
    }

    /**
     * @expectedException \Zend\View\Exception\InvalidArgumentException
     */
    public function testThrowsExceptionOnInvalidParams()
    {
        $this->url->__invoke('default', 'invalid params');
    }

    public function testPluginWithoutRouteMatchesInEventRaisesExceptionWhenNoRouteProvided()
    {
        $this->setExpectedException('Zend\View\Exception\RuntimeException', 'RouteMatch');
        $this->url->__invoke();
    }

    public function testPluginWithRouteMatchesReturningNoMatchedRouteNameRaisesExceptionWhenNoRouteProvided()
    {
        $this->url->setRouteMatch(new RouteMatch([]));
        $this->setExpectedException('Zend\View\Exception\RuntimeException', 'matched');
        $this->url->__invoke();
    }

    public function testPassingNoArgumentsWithValidRouteMatchGeneratesUrl()
    {
        $routeMatch = new RouteMatch([]);
        $routeMatch->setMatchedRouteName('home');
        $this->url->setRouteMatch($routeMatch);
        $url = $this->url->__invoke();
        $this->assertEquals('/', $url);
    }

    public function testCanReuseMatchedParameters()
    {
        $this->router->addRoute('replace', [
            'type'    => 'Zend\Mvc\Router\Http\Segment',
            'options' => [
                'route'    => '/:controller/:action',
                'defaults' => [
                    'controller' => 'ZendTest\Mvc\Controller\TestAsset\SampleController',
                ],
            ],
        ]);
        $routeMatch = new RouteMatch([
            'controller' => 'foo',
        ]);
        $routeMatch->setMatchedRouteName('replace');
        $this->url->setRouteMatch($routeMatch);
        $url = $this->url->__invoke('replace', ['action' => 'bar'], [], true);
        $this->assertEquals('/foo/bar', $url);
    }

    public function testCanPassBooleanValueForThirdArgumentToAllowReusingRouteMatches()
    {
        $this->router->addRoute('replace', [
            'type' => 'Zend\Mvc\Router\Http\Segment',
            'options' => [
                'route'    => '/:controller/:action',
                'defaults' => [
                    'controller' => 'ZendTest\Mvc\Controller\TestAsset\SampleController',
                ],
            ],
        ]);
        $routeMatch = new RouteMatch([
            'controller' => 'foo',
        ]);
        $routeMatch->setMatchedRouteName('replace');
        $this->url->setRouteMatch($routeMatch);
        $url = $this->url->__invoke('replace', ['action' => 'bar'], true);
        $this->assertEquals('/foo/bar', $url);
    }

    public function testRemovesModuleRouteListenerParamsWhenReusingMatchedParameters()
    {
        $router = new \Zend\Mvc\Router\Http\TreeRouteStack;
        $router->addRoute('default', [
            'type' => 'Zend\Mvc\Router\Http\Segment',
            'options' => [
                'route'    => '/:controller/:action',
                'defaults' => [
                    ModuleRouteListener::MODULE_NAMESPACE => 'ZendTest\Mvc\Controller\TestAsset',
                    'controller' => 'SampleController',
                    'action'     => 'Dash'
                ]
            ],
            'child_routes' => [
                'wildcard' => [
                    'type'    => 'Zend\Mvc\Router\Http\Wildcard',
                    'options' => [
                        'param_delimiter'     => '=',
                        'key_value_delimiter' => '%'
                    ]
                ]
            ]
        ]);

        $routeMatch = new RouteMatch([
            ModuleRouteListener::MODULE_NAMESPACE => 'ZendTest\Mvc\Controller\TestAsset',
            'controller' => 'Rainbow'
        ]);
        $routeMatch->setMatchedRouteName('default/wildcard');

        $event = new MvcEvent();
        $event->setRouter($router)
              ->setRouteMatch($routeMatch);

        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->onRoute($event);

        $helper = new UrlHelper();
        $helper->setRouter($router);
        $helper->setRouteMatch($routeMatch);

        $url = $helper->__invoke('default/wildcard', ['Twenty' => 'Cooler'], true);
        $this->assertEquals('/Rainbow/Dash=Twenty%Cooler', $url);
    }

    public function testAcceptsNextGenRouterToSetRouter()
    {
        $router = new NextGenRouter();
        $url = new UrlHelper();
        $url->setRouter($router);
        $this->assertAttributeSame($router, 'router', $url);
    }

    public function testAcceptsNextGenRouteMatche()
    {
        $routeMatch = new NextGenRouteMatch([]);
        $url = new UrlHelper();
        $url->setRouteMatch($routeMatch);
        $this->assertAttributeSame($routeMatch, 'routeMatch', $url);
    }
}
