<?

class Controller {

    public static $AutoRun = "_autorun";

    public static $autoRunFile = null;

    public static $getKey = "AppController";

    public static $fullRequest = null;

    /**
     * The HTTP method used for this HTTP transaction
     * @var string
     */
    public static $requestMethod = null;

    /**
     * The absolute file path where the controllers are actually stored
     * @var string
     */
    public static $controllerPath = "";

    /**
     * The absolute file path to the current controller being called
     * @var string
     */
    public static $controllerFile;

    public static $pageController = null;

    /**
     * The external HTTP URI for the current controller
     * @var string
     */
    public static $pageRequest;

    public static $uri = null;

    /**
     * The name of the current action being called
     * @var string
     */
    public static $action;

    /**
     * The name of the default action
     * Default "index"
     * @var string
     */
    public static $defaultAction = "index";

    /**
     * Flag to specify if the supplied "page" (controller/action) was valid
     * Default false
     * @var bool
     */
    public static $validPage = false;

    /**
     * Flag to specify if the default "page" (controller/action) is to be used
     * Default false
     * @var bool
     */
    public static $defaultPage = false;

    /**
     * Flag to specify if the current request is an AJAX request
     * Default false
     * @var bool
     */
    public static $isAjax = false;

    public static $params = Array();

    /**
     * An array of HTTP GET variables passed to the framework
     * @var Array
     */
    public static $httpGetParams = Array();

    /**
     * An array of HTTP POST variables passed to the framework
     * @var Array
     */
    public static $httpPostParams = Array();

    public static $rawParams = Array();

    /**
     * The default number of results per page
     * @var integer
     */
    public static $defaultPageNumber = 25;

    /**
     * Used to store the AJAX request's JSON response code in array format
     * @var Array
     */
    private $json = Array();

    /**
     * This flag tells the controller to render the view automatically.
     * Default is true;
     * @var bool
     */
    private $autoRender = true;

    public function __construct() {
        /**
         * Detect if this request is AJAX or not.  We do this because the controllers
         * handles AJAX requests differently than regular ones.
         */
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest") {
            $this->isAjax = true;
        }
        
        self::mapKeys(null, $this);
    }

    public function __destruct() {
        /**
         * Only render the view if Controller::$autoRender is set to true
         * Controller::$autoRender defaults to true
         */
        if ($this->autoRender === true) {
            /**
             * Decide on how to handle the return for the request
             * based on if it was AJAX or not.
             */
            if ($this->isAjax === true) {
                $this->sendJson();
            } else {
                /**
                 * Since the request is not an AJAX one, we try and display a View
                 * based on the action that was called
                 */
                View::display(self::$action);
            }
        }
    }

    public static function init($path = null) {
        /**
         * If there is no controller path passed, throw an error
         */
        if ($path === null) {
            throw new EBadControllerPath("No controller path was specified.");
        }
        
        self::$controllerPath = @realpath($path);
        
        /**
         * If the controller path provided does not exist, throw an error
         */
        if (! self::$controllerPath) {
            throw new EBadControllerPath("The controller path '" . self::$controllerPath . "' does not exist.");
        }
        
        /**
         * Make sure the controller path ends with a /
         */
        if (substr(self::$controllerPath, - 1) != "/") {
            self::$controllerPath .= "/";
        }
        
        /**
         * Set the request type, defaulting to GET
         */
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            self::$requestMethod = 'POST';
        } else {
            self::$requestMethod = 'GET';
        }
        
        self::parseArgs();
    }

    /* Generally invoked by Controller::init() */
    protected static function parseArgs() {
        self::$fullRequest = ( isset($_GET[self::$getKey]) ) ? $_GET[self::$getKey] : "";
        self::$fullRequest = "/" . self::$fullRequest;
        
        $parts = explode("/", ltrim(self::$fullRequest, "/"));
        
        if (! $parts) {
            $parts[] = "";
        }
        
        $controllerFileAbsolutePath = self::$controllerPath;
        $uri = "";
        $count = count($parts);
        
        /**
         * If there is no page specified, assume it's the index
         */
        if (self::$fullRequest == "/") {
            $parts[0] = "index";
        }
        
        /**
         * Break down the requested path to find a valid controller
         * Check if each request element is a directory and continue through
         * the loop if it is, otherwise check if it is a file in the current
         * directory element, which will be our controller file
         * If there is no controller file check for an index and use that instead
         * If all else fails, prepare to throw an EBadController exception
         */
        for($x = 0; $x <= $count; $x++) {
            if ($parts[$x] != "" && is_dir($controllerFileAbsolutePath . $parts[$x])) {
                $controllerFileAbsolutePath .= "$parts[$x]/";
                $uri .= "$parts[$x]/";
                
                continue;
            } elseif (file_exists($controllerFileAbsolutePath . "$parts[$x].ctrl.php")) {
                $controllerFileAbsolutePath .= "$parts[$x]";
                $uri .= "$parts[$x]/";
                
                self::$validPage = true;
                self::$pageController = ucfirst($parts[$x]);
                
                break;
            } else {
                if (file_exists($controllerFileAbsolutePath . "index.ctrl.php")) {
                    $controllerFileAbsolutePath .= "index";
                    self::$validPage = true;
                    self::$defaultPage = true;
                    self::$pageController = "Index";
                    break;
                }
                $invalidPage = $parts[$x];
                break;
            }
        }
        
        self::$uri = $uri;
        
        /**
         * If a valid controller was found, set some static variables and return true
         * Otherwise throw an invalid page exception
         */
        if (self::$validPage !== false) {
            $count = count(explode("/", $uri)) - 1; //Subtract one because the $parts array index starts at 0
            

            self::$action = $parts[$count];
            
            if (self::$action == "index") {
                self::$rawParams = array_slice($parts, $count);
            } else {
                self::$rawParams = array_slice($parts, ( $count + 1 )); //Add one to discard the action
            }
            
            self::$controllerFile = "$controllerFileAbsolutePath.ctrl.php";
            
            self::$pageRequest = "/" . implode("/", $parts);
            
            return true;
        } else {
            throw new EBadController("The controller '" . $invalidPage . "' does not exist.");
        }
    }

    public static function mapKeys($keys = Array(), $controller) {
        if (method_exists($controller, self::$action)) {
            $method = new ReflectionMethod($controller, self::$action);
        } else {
            $method = new ReflectionMethod($controller, "index");
        }
        $params = $method->getParameters();
        
        foreach($params as $key => $param) {
            self::$params[$param->name] = self::$rawParams[$key];
        }
        
        if (self::$requestMethod == 'GET') {
            foreach($_GET as $key => $val) {
                self::$httpGetParams[$key] = $val;
            }
        } else {
            foreach($_POST as $key => $val) {
                self::$httpPostParams[$key] = $val;
            }
        }
    }

    public function setJson($json) {
        $this->json = json_encode($json);
    }

    public function getJson() {
        return $this->json;
    }

    public function sendJson() {
        header("Content-Type: text/plain; charset=utf-8;\r\n");
        echo $this->getJson();
    }

    /**
     * Sets the current controller instance's auto view render functionality on / off
     * 
     * @param true|false $set
     */
    public function setAutoRender($set = true) {
        $this->autoRender = (bool) $set;
    }
}