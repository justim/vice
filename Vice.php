<?php

/**
 * Vice is a small web framework for easy dispatching actions for a given URL
 *
 * @author justim
 *
 * vice - /vīs/
 *
 * Noun
 * Wicked behavior
 *
 * Combining form
 * Acting as deputy or substitute for; next in rank: "vice framework"
 */
class Vice
{
	// list of all the routes
	private $_routes;

	// Available filters
	private $_availableFilters = [];

	// base path
	private $_basePath;

	// store for arbitrary information
	private $_store;

	/**
	 * Create a app
	 * @param string the base path, all routes are relative to this path,
	 * 					http://localhost/vice/example -> /vice/example
	 * @param array arbitrary information that will be available in all actions (also in subapps)
	 */
	public function __construct($basePath = '/', array $store = [])
	{
		$this->_basePath = rtrim($basePath, '/') . '/';
		$this->_store = $store;

		// setup default filters
		$this->registerFilters([
			'is:ajax'    => [ $this, '_isAjax' ],
			'is:get'     => [ $this, '_isGet' ],
			'is:post'    => [ $this, '_isPost' ],
			'is:put'     => [ $this, '_isPut' ],
			'is:delete'  => [ $this, '_isDelete' ],
		]);
	}

	/**
	 * Helper function to create a route
	 * Possible methods:
	 *   -  route   create a route that listens to all requests
	 *   -  get     create a route that only listens to GET-requests
	 *   -  post    create a route that only listens to POST-requests
	 *   -  put     create a route that only listens to PUT-requests
	 *   -  delete  create a route that only listens to DELETE-requests
	 * @param string the route
	 * @param [string] filters (ex.: is:ajax) (optional argument)
	 *          -  is:ajax    the request is an ajax request
	 *          -  is:get     the request is an GET-request
	 *          -  is:post    the request is an POST-request
	 *          -  is:put     the request is an PUT-request
	 *          -  is:delete  the request is an DELETE-request
	 *          -  any other user defined filter
	 * @param Callable|self the action or a subapp that is called when the route is matched
	 */
	public function __call($method, $arguments)
	{
		// list of methods with their predefined filters
		$listOfMethods = [
			'route'   => '',
			'get'     => 'is:get',
			'post'    => 'is:post',
			'put'     => 'is:put',
			'delete'  => 'is:delete',
			'ajax'    => 'is:ajax',
		];

		if (isset($listOfMethods[$method]))
		{
			// we fiddle a bit with the arguments, so we need to extract/validate them ourselves
			$route = array_shift($arguments);
			if ($route === null)
			{
				throw new BadMethodCallException('Route is mandatory');
			}

			$action = array_pop($arguments);
			if (is_callable($action) === false)
			{
				throw new BadMethodCallException('Action needs to be specified');
			}

			$filters = trim($listOfMethods[$method] . ' ' . (string) current($arguments));

			return $this->_addRoute($route, $filters, $action);
		}
		else
		{
			throw new BadMethodCallException('Method does not exist [' . $method . ']');
		}
	}

	/**
	 * Register a filter for your application
	 * @param string the name for your filter
	 * @param [string] filters (ex.: is:ajax) (optional argument)
	 * @param Callable the filter, you should return `true` when it passes
	 */
	public function registerFilter()
	{
		$arguments = func_get_args();

		// we fiddle a bit with the arguments, so we need to extract/validate them ourselves
		$name = array_shift($arguments);
		if ($name === null)
		{
			throw new BadMethodCallException('Name is mandatory');
		}

		$filter = array_pop($arguments);
		if (is_callable($filter) === false)
		{
			throw new BadMethodCallException('Filter needs to be specified');
		}

		if (array_key_exists($name, $this->_availableFilters) === false)
		{
			$this->_availableFilters[$name] = [
				'name' => $name,
				'filter' => $filter,
				'filters' => $this->_generateFilters((string) current($arguments)),
			];

			return $this;
		}
		else
		{
			throw new BadMethodCallException('Filter already defined [' . $name . ']');
		}
	}

	/**
	 * Register multiple filters for your application
	 * @param array list of filters
	 */
	public function registerFilters(array $filters)
	{
		foreach ($filters as $filterName => $filter)
		{
			$this->registerFilter($filterName, $filter);
		}

		return $this;
	}

	/**
	 * Shortcut for running the app
	 */
	public function __invoke()
	{
		return $this->run();
	}

	/**
	 * Run the application
	 * Sets a 404 header when no route was matched
	 * @return boolean a route was matched
	 */
	public function run()
	{
		$result = $this->_run($this->_getUri());

		if ($result === false)
		{
			if (headers_sent() === false)
			{
				header('HTTP/1.1 404 Not Found');
			}

			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Run the application for a given URI
	 * @param string the URI we want to run the application against
	 * @param array associative list of params that came from the URI
	 * 				(a subapp will receive the params from the parent app)
	 * @param array store with arbitrary information
	 * 				(a subapp will receive the store from the parent app)
	 */
	private function _run($uri, array $requestParams = [], array $store = [], array $availableFilters = [], array $filterResults = [])
	{
		// merge the current store and filters (with results) with the one from the parent app
		$store = $this->_store + $store;
		$availableFilters = $this->_availableFilters + $availableFilters;

		foreach ($this->_routes as $route => $meta)
		{
			if ($this->_matchUri($meta['route'], $uri, $requestParams))
			{
				// run all filters (ex.: http method validation)
				$filtersResult = $this->_runFilters($meta['filters'], $requestParams, $store, $filterResults);

				if ($filtersResult === false)
				{
					continue;
				}

				// detection of a subapp
				if ($meta['action'] instanceof self)
				{
					$subViceMatched = $meta['action']->_run(
						preg_replace($meta['route'], '/', $uri), // strip the prefix
						$requestParams,
						$store,
						$availableFilters,
						$filterResults
						);

					if ($subViceMatched === false)
					{
						// subapp did not deliver a satifying result, we're just
						// continuing matching routes in this application
						continue;
					}
				}
				else
				{
					// call the action
					$this->_dispatch($meta['action'], $requestParams, $store, $filterResults);
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Match uri against a route
	 * @param string route
	 * @param string uri
	 * @param array matched request params from the uri
	 */
	private function _matchUri($route, $uri, &$requestParams)
	{
		$result = preg_match($route, $uri, $matches);

		if ($result)
		{
			// process all the params from the URI
			foreach ($matches as $key => $match)
			{
				if (is_string($key)) // only named matches
				{
					$requestParams[$key] = $match;
				}
			}
		}

		return $result;
	}

	/**
	 * Run recursively through a list of filters and return true when they all pass
	 * @param array list of filters
	 * @param array request params for this uri
	 * @param array data store for arbitrary information
	 */
	private function _runFilters($filters, $requestParams, $store, &$filterResults)
	{
		$tmpFilterResults = $filterResults;

		foreach ($filters as $filter)
		{
			// a filter can have "previous" filters, run them recursively
			if (!empty($filter['filters']))
			{
				$filtersResult = $this->_runFilters($filter['filters'], $requestParams, $store, $tmpFilterResults);

				if ($filtersResult === false)
				{
					return false;
				}
			}

			// use _dispatch method to inject all kinds of nice things into the filter callback
			$tmpFilterResults[$filter['name']] = $this->_dispatch($filter['filter'], $requestParams, $store, $tmpFilterResults);

			if ($tmpFilterResults[$filter['name']] === false)
			{
				return false;
			}
		}

		// only merge with the filter results when all filters are run successfully
		$filterResults = array_merge($tmpFilterResults);

		return true;
	}

	/**
	 * Execute a given callback with reflection parameters
	 * @param Callable the action that needs executing
	 * @param array list of parameters from the URI
	 * @param array the data store for arbitrary information
	 */
	private function _dispatch(Callable $callback, array $requestParams, array $store, array $rawFilterResults)
	{
		$reflection = $this->_createReflectionClass($callback);
		$functionParameters = $reflection->getParameters();

		// filters can have colons in their name, variables don't
		$filterResults = $this->_changeKeys($rawFilterResults, function($key)
		{
			return str_replace([ ':', ' ', '/' ], '', $key);
		});

		// remove the `_method` from the post, its for internal use
		$filteredPost = $_POST;
		unset($filteredPost['_method']);

		$parameterHelper = $this->_createParameterHelper([ $requestParams, $store, $filterResults ]);

		// loop through all parameters and find a nice value for it based on it's name
		// (ex.: the variable $ajax would contain a boolean if the request was an ajax one)
		$args = [];
		$i = 0;
		foreach ($functionParameters as $parameter)
		{
			$name = strtolower($parameter->getName());

			switch ($name)
			{
				case 'post':     $args[$i] = $this->_createParameterHelper([ $filteredPost ]);  break;
				case 'get':      $args[$i] = $this->_createParameterHelper([ $_GET ]);          break;
				case 'param':    $args[$i] = $this->_createParameterHelper([ $requestParams ]); break;
				case 'server':   $args[$i] = $this->_createParameterHelper([ $_SERVER ]);       break;
				case 'store':    $args[$i] = $this->_createParameterHelper([ $store ]);         break;
				case 'filter':   $args[$i] = $this->_createParameterHelper([ $filterResults ]); break;
				case 'ajax':     $args[$i] = $this->_isAjax();                                  break;
				case 'json':     $args[$i] = $this->_createJsonHelper();                        break;
				case 'redirect': $args[$i] = $this->_createRedirectHelper();                    break;
				default:         $args[$i] = $parameterHelper($name);                           break;
			}

			$i++;
		}

		return call_user_func_array($callback, $args);
	}

	/**
	 * Helper function to create a reflection(method|function) class
	 * @param Callable
	 */
	private function _createReflectionClass(Callable $callback)
	{
		if (is_array($callback))
		{
			return new ReflectionMethod($callback[0], $callback[1]);
		}
		else if (is_string($callback) && strpos($callback, '::') !== false)
		{
			list($class, $method) = explode('::');
			return new ReflectionMethod($class, $method);
		}
		else if (method_exists($callback, '__invoke'))
		{
			return new ReflectionMethod($callback, '__invoke');
		}
		else
		{
			return new ReflectionFunction($callback);
		}
	}

	/**
	 * Create a helper for a list of sources to get their value and a default
	 * @param array list of source (ex.: $_GET)
	 */
	private function _createParameterHelper(array $sources)
	{
		$combinedSources = [];

		// combine the sources
		foreach ($sources as $source)
		{
			$combinedSources += $source;
		}

		return function($name = null, $default = null) use ($combinedSources)
		{
			// when no key is give, give the source
			if ($name === null)
			{
				return $combinedSources;
			}
			else if (array_key_exists($name, $combinedSources))
			{
				return $combinedSources[$name];
			}
			else
			{
				return $default;
			}
		};
	}

	/**
	 * Create a helper for easily responding in json
	 */
	private function _createJsonHelper()
	{
		return function($data, $exit = true)
		{
			header('Content-Type: application/json');
			echo json_encode($data);

			if ($exit)
			{
				exit;
			}
		};
	}

	/**
	 * Create a helper to redirect an user to a different URI
	 */
	private function _createRedirectHelper()
	{
		return function($uri, $exit = true)
		{
			header('Location: ' . $uri);

			if ($exit)
			{
				exit;
			}
		};
	}

	/**
	 * Is the current request an AJAX-request
	 */
	private function _isAjax($server)
	{
		return $server('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest';
	}

	/**
	 * Is the current request a GET-request
	 */
	private function _isGet($server)
	{
		return $server('REQUEST_METHOD') === 'GET';
	}

	/**
	 * Is the current request a POST-request
	 */
	private function _isPost($server)
	{
		return $server('REQUEST_METHOD') === 'POST';
	}

	/**
	 * Is the current request a PUT-request
	 * This is also triggered when a _POST-field named `_method` has the value PUT
	 */
	private function _isPut($server)
	{
		$post = $this->_createParameterHelper([ $_POST ]);
		return $server('REQUEST_METHOD') === 'POST' && $post('_method') === 'PUT';
	}

	/**
	 * Is the current request a DELETE-request
	 * This is also triggered when a _POST-field named `_method` has the value DELETE
	 */
	private function _isDelete($server)
	{
		$post = $this->_createParameterHelper([ $_POST ]);
		return $server('REQUEST_METHOD') === 'POST' && $post('_method') === 'DELETE';
	}

	/**
	 * Get the URI based on the _SERVER-variable
	 * Sources:
	 * 	-	REDIRECT_URL
	 * 	-	REQUEST_URI
	 */
	private function _getUri()
	{
		$server = $this->_createParameterHelper([ $_SERVER ]);
		$redirectUrl = $server('REDIRECT_URL');

		if (!empty($redirectUrl))
		{
			$uri = $redirectUrl;
		}
		else
		{
			$uri = parse_url($server('REQUEST_URI'))['path'];
		}

		return $uri;
	}

	/**
	 * Add a route and it's corresponding action
	 * @param string filters (ex. is:ajax)
	 * @param string the route we want the action to listen on
	 * @param Callable|self the action associated with this route, or a complete app
	 */
	private function _addRoute($route, $filters, Callable $action)
	{
		$this->_routes[] = [
			'route' => static::_generateRegexFromRoute(
				$this->_basePath . ltrim($route, '/'),
				$action instanceof self
				),
			'filters' => $this->_generateFilters($filters),
			'action' => $action,
			];

		return $this;
	}

	/**
	 * Generate a regex based on a route
	 * @param string the route we want to match
	 * @param boolean the route is a prefix
	 */
	private static function _generateRegexFromRoute($rawRoute, $prefix = false)
	{
		// make the route safe for a regex
		$routeRegex = preg_quote(trim($rawRoute), '/');

		// replace placeholder with a regex version
		$routeRegex = preg_replace('/\\\<([a-z]+?)\\\>/i', '(?<$1>[a-z0-9-_]+?)', $routeRegex);

		// make sure we have a trailing slash
		if (substr($routeRegex, -1) !== '/')
		{
			$routeRegex .= '\/';
		}

		// trailing slash is optional
		$routeRegex .= '?';

		// when we're not matching partial route, match for the complete route
		if ($prefix === false)
		{
			$routeRegex .= '$';
		}

		return "/^$routeRegex/i";
	}

	/**
	 * Generate filters based on our own filter format
	 * @param string the filters
	 */
	private function _generateFilters($rawFilters)
	{
		$filters = [];

		foreach ($this->_availableFilters as $filterName => $filter)
		{
			if (preg_match('/(?<negative>!)?\b' . preg_quote($filterName, '/') . '\b/i', $rawFilters, $matches))
			{
				if (!empty($matches['negative']))
				{
					$filter['filter'] = function() use ($filter)
					{
						return !$filter['filter']();
					};
				}

				$filters[strtolower($filterName)] = $filter;
			}
		}

		// we want the filters to be in same order as the user supplied them
		// because filter names can be anything there is isn't another way
		// to keep the order same and associating the correct filter, ex.:
		// explode on spaces would break all filters with a space in their name
		uksort($filters, function($first, $second) use ($rawFilters)
		{
			return strpos($rawFilters, $first) - strpos($rawFilters, $second);
		});

		return $filters;
	}

	/**
	 * Helper function for easy key changing
	 */
	private function _changeKeys($list, Callable $callback)
	{
		return array_combine(array_map($callback, array_keys($list)), array_values($list));
	}
}
