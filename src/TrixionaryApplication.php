<?php
namespace gossi\trixionary\app;

use keeko\framework\foundation\AbstractApplication;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use gossi\trixionary\model\SportQuery;

/**
 * Trixionary App
 *
 * @license MIT
 * @author gossi
 */
class TrixionaryApplication extends AbstractApplication {

	/**
	 * @param Request $request
	 * @param string $path
	 */
	public function run(Request $request) {
		$kernel = $this->getServiceContainer()->getKernel();
		$account = $this->getServiceContainer()->getModuleManager()->load('keeko/account');
		$trixionary = $this->getServiceContainer()->getModuleManager()->load('gossi/trixionary-client');

		$widget = $account->loadAction('account-widget', 'html');
		$widget = $kernel->handle($widget, $request);

		$this->getPage()->setDefaultTitle('Trixionary');
		$this->getPage()->setTitlePrefix('Trixionary:');

		try {
			$routes = $this->generateRoutes();
			$context = new RequestContext($this->getBaseUrl());
			$matcher = new UrlMatcher($routes, $context);

			$match = $matcher->match($this->getDestination());
			$route = $match['_route'];
			$action = null;

			switch ($route) {
				case 'account':
				case 'account-index':
					$action = $account->loadAction('account', 'html');
					$action->setBaseUrl($this->getBaseUrl() . '/account');
					$action->setDestination(str_replace($action->getBaseUrl(), '', $request->getUri()));
					break;

				case 'trixionary':
				default:
					$action = $trixionary->loadAction('trixionary');
					$action->setBaseUrl($this->getBaseUrl());
					$action->setDestination($this->getDestination());
					break;
			}

			$kernel = $this->getServiceContainer()->getKernel();
			$response = $kernel->handle($action, $request);

			if ($response instanceof RedirectResponse) {
				return $response;
			}

			$main = $response->getContent();
		} catch (PermissionDeniedException $e) {
			$main = 'Permission Denied';
		} catch (\Exception $e) {
			$main = 'Error: ' . $e->getMessage();
		}


		$sports = SportQuery::create()->orderByTitle()->find();
		$response = new Response();
		$response->setContent($this->render('/gossi/trixionary-app/templates/main.twig', [
			'account_widget' => $widget->getContent(),
			'main' => $main,
			'sports' => $sports
		]));

		return $response;
	}

	/**
	 *
	 * @return RouteCollection
	 */
	private function generateRoutes() {
		$routes = new RouteCollection();
		$routes->add('account-index', new Route('/account'));
		$routes->add('account', new Route('/account/{suffix}', ['suffix' => ''], ['suffix' => '.*']));
		$routes->add('trixionary', new Route('/{suffix}', ['suffix' => ''], ['suffix' => '.*']));

		return $routes;
	}
}
