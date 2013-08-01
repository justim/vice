# Vice

Vice is a small web framework for easy dispatching actions for a given URL.

It uses some nifty tricks to make sure you don't have to;
* Easy restriction of HTTP-methods
* Simple AJAX response
* Arguments filled based on their name, for easy access
* Named parameters in the URI
* Powerfull filters

## Requirements
* PHP >= 5.4

## Example application
You can check the `example`-directory in this repository to see it all in action. You can run the example with PHPs builtin server:

	php -S localhost:9000 -t example/

Now open [http://localhost:9000](http://localhost:9000) in your browser to see the example.

## Examples

### Basic example
```php
$app = new Vice;
$app->route('/', function($render)
{
    // $render is a builtin helper to get a simple template on your screen
    echo $render('index.php', [ /* view */ ]);
});
$app_>run();
```

### JSON Helper
```php
$app = new Vice;

// route that only responds to ajax-post requests
$app->post('/users', 'is:ajax', function($json)
{
    // builtin helper, sets a header, encodes it, and dies
    $json([
        [
            'name' => 'Pizza kat',
        ]
    ]);
});
```

### Global store
```php
$app = new Vice('/', [
    'db' => new Database, // some database connection
]);

$app->get('/', function($db)
{
    // $db is the one that you put in the store
});
```

### Advanced filters
```php
$app = new Vice;
$app->registerFilter('is:logged', function()
{
    if (/* some login check */)
    {
        return [
            'name' => 'Flavored mushrooms',
        ];
    }
    else
    {
        return false;
    }
});

$app->get('/admin', 'is:logged', function($isLogged)
{
    // $isLogged would contain the result of the filter,
	// if the filter fails, then this function is never called
	echo 'Hello ' . $isLogged['name'] . '!';
});
```

### Subapps
```php
$app = new Vice('/', [ 'db' => new Database ]);
$users = new Vice;

$users->get('<id>', function($json, $db, $id, $param)
{
    // $param('id') === $id
    $json($db->users->get($id));
});

// users subapp is available at /users and only for ajax-get-requests
// this would make the route /users/1 available
$app->get('users', 'is:ajax', $users);
```
_Subapps share the filters and store from their parent, but not the other way around._

### Multiple filters chained
```php
$app = new Vice('/', [ 'currentUser' => 1, 'db' => new Database ]);

$app->registerFilter('is:logged', function($db, $currentUser)
{
    // check for existince of current user and return it
    return $db->users->get($currentUser) ?: false;
});

$app->registerFilter('is:admin', function($isLogged)
{
    return $isLogged['admin'] === true;
});

$app->route('admin', 'is:logged is:admin', function($isLogged)
{
    echo 'Hello ' . $isLogged['name'] . ', you are an admin!';
});
```

## Builtin filters
* GET-request
* POST-request
* PUT-request
* DELETE-request
* AJAX-request

_(by `$app->get(..)` or as a filter `$app->route('/', 'is:get')` (same for all others)_

## Builtin named arguments
* `$post` -> `function($key, $default = null)`-wrapper for `$_POST`
* `$get` -> `function($key, $default = null)`-wrapper for `$_GET`
* `$param` -> `function($key, $default = null)`-wrapper for the parameters in the URI
* `$server` -> `function($key, $default = null)`-wrapper for `$_SERVER`
* `$store` -> `function($key, $default = null)`-wrapper for the global store
* `$filter` -> `function($key, $default = null)`-wrapper for passed filter results
* `$ajax` -> `boolean` that tells you if the request is an AJAX one
* `$json` -> `function($data)` JSON helper
* `$render` -> `function($template, $data = [])` little template renderer, searches for template in `./templates/`
* Keys from the `params`, `store`, `filterResults` (in that order)

## Some technical insights
* Every route is compiled to a regex, which is then matched against the current URI. When a match is found all the defined filters for that route are tested. If it is still a match, then we run the corresponding action. When the action is an app itself, the whole process is ran again, but without the prefix we already matched. After some looping something probably happened and we're done.
* `Reflection` is used to determine the value of the arguments
* It's a single class, everything else is a function
