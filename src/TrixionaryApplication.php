<?php
namespace gossi\trixionary\app;

use keeko\core\application\AbstractApplication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use keeko\core\exceptions\PermissionDeniedException;

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
		try {
			if ($this->getDestinationPath() == '/login') {
				$main = $this->login($request);
			} else {
				$moduleManager = $this->getServiceContainer()->getModuleManager();
				$client = $moduleManager->load('gossi/trixionary-client');
				$router = $client->loadAction('router', 'html');
				$main = $this->runAction($router, $request);
			}
			
			if ($main instanceof RedirectResponse) {
				return $main;
			}
		} catch (PermissionDeniedException $e) {
			$main = new Response('<h1>Permission Denied</h1><p>' .$e->getMessage() . '</p>');
		}
		
		$prefs = $this->getServiceContainer()->getPreferenceLoader()->getSystemPreferences();
		$this->getPage()->setDefaultTitle($prefs->getPlattformName() . ' Trixionary');
		$this->getPage()->setTitleSuffix('· ' . $prefs->getPlattformName() . ' Trixionary');

		return $this->render('main.twig', [
			'main' => $main->getContent(),
			'page' => $this->getPage(),
			'stylesheets' => $this->getPage()->getStyles(),
			'scripts' => $this->getPage()->getScripts()
		]);
	}
	
	public function getTargetPath() {
		return '';
	}
	
	private function login(Request $request) {
		$username = '';
		$error = '';
		$redirect = $request->headers->get('referer');
		
		// try login
		if ($request->isMethod('POST')) {
			$username = $request->request->get('username');
			$password = $request->request->get('password');
			$redirect = $request->request->get('redirect');
			
			$auth = $this->getServiceContainer()->getAuthManager();
			if ($auth->login($username, $password)) {
				$token = $auth->getAuth()->getToken();
				$foward = $redirect ?: $this->getAppUrl();
				$response = new RedirectResponse($foward);
				$response->headers->setCookie(new Cookie('Bearer', $token));
				return $response;
			}
			
			$error = 'Invalid credentials';
		}
		
		$twig = $this->getTwig();
		return new Response($twig->render('login.twig', [
				'error' => $error,
				'base' => $this->getAppUrl(),
				'redirect' => $redirect,
				'username' => $username
			]));
	}
}
