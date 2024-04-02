<?php
namespace Amqp;

use Psr\Http\Message\ResponseInterface;
use Amqp\Message\Hydrator\MessageHydrator;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Validator\ValidatorInterface;

/**
 * Message object looks like HTTP message, pipeline'd like HTTP message and respond like a HTTP message.
 * Application pipeline does not see difference. Message IS an usable HTTP Request and Response both.
 *
 * @todo implement JsonSerializable
 */
class Message extends ServerRequest implements
    \ArrayAccess,           // Quick access to headers
    ResponseInterface,      // Handler can return Message for an RPC reply.
    ValidatorInterface      // Specific classes may enforce some fields or headers.
{
    use PipeableTrait;

    /** Of App's message protocol. */
    const VERSION = '1.0';

    const PRIORITY_LOW     = 0;
    const PRIORITY_NORMAL  = 1;
    const PRIORITY_HIGH    = 2;
    const PRIORITY_EMERG   = 3;

    /**
     * @var array List of object properties that will always be copied as headers.
     *            If property not exists, header will be skipped.
     */
    protected array $PROPS_TO_HEADERS = ['id', 'refid', 'state', 'agent', 'client'];

    protected $defaultHeaders = [
        'version' => self::VERSION, // required to match all
        'priority' => self::PRIORITY_NORMAL,
        'expiration' => 300000
    ];
    protected $statusCode = 200;
    protected $reasonPhrase = 'OK';

    protected MessageHydrator $hydrator;

    /**
     * Messages that explain why the most recent isValid() call returned false.
     * The array keys are validation failure message identifiers,
     * and the array values are the corresponding human-readable message strings.
     */
    protected array $errorMessages = [];

    /**
     * Place to hydrate a rabbit message to an object.
     *
     * @param \Bunny\Message $bunny
     * @return self
     */
    static public function factory($sm, $name = null, $opts = []) : self
    {
        @$bunny = $opts['bunnyMessage'] ?: $opts[0];
        if (!$bunny instanceof \Bunny\Message) {
            throw new \InvalidArgumentException("Message must be built with bunnyMessage opt.");
        }

        // Use specific class for the message if available
        $guess = [];
        if (($resource = $bunny->getHeader('resource')) && ($endpoint = $bunny->getHeader('endpoint'))) {
            $class = ucfirst($resource) . '\\' . ucfirst($resource) . ucfirst($endpoint) . 'Message';
            $guess[] = "\\App\\Amqp\\Message\\$class";
            $guess[] = "\\X\\Amqp\\Message\\$class";
        }
        $guess[] = '\\App\\Amqp\\Message';
        $guess[] = '\\X\\Amqp\\Message';

        // first match wins
        foreach ($guess as $class) {
            if (class_exists($class)) break;
        }

        // convert back DateTime that bunny created
        foreach ($bunny->headers as &$header) {
            if ($header instanceof \DateTime) {
                $header = $header->format('Y-m-d H:i:s');
            }
        }

        $self = new $class($bunny->content, $bunny->headers);
        return $self
            ->withAttribute('exchange',    $bunny->exchange)
            ->withAttribute('rkey',        $bunny->routingKey)
            ->withAttribute('redelivered', $bunny->redelivered)
            ->withAttribute('deliveryTag', $bunny->deliveryTag)
            ->withAttribute('consumerTag', $bunny->consumerTag)
        ;
    }

    /**
     * Create a message to publish.
     * Array elements are headers and properties are JSON body elements.
     *
     * @param string|array|object       $body    Array of props or any string.
     * @param array                     $headers Flat key-value pairs.
     */
    public function __construct($body = '', array $headers = [])
    {
        // Get object props from given argument of mixed type.
        if (is_array($body)) {
            $props = $body;
            $body = json_encode_pretty($props);
        } else {
            $body = (string) $body;
            $try = @json_decode($body, true);
            if (is_array($try)) $props = $try;
            else $props = [];
        }

        // Populate props to the instance
        if ($props) {
            $this->getHydrator()->hydrate($props, $this);
            $props = $this->getHydrator()->extract($this); // to filter what's in body
            $body = json_encode_pretty($body);
        }

        if (property_exists($this, 'created') && empty($this->created)) {
            $this->created = new \DateTime;
        }


        // inject routing headers from class name
        $matches = [];
        $class = array_reverse(explode('\\', get_class($this)))[0];
        if (preg_match_all('/([A-Z].+?)([A-Z].+?)Message/', $class, $matches)) {
             // one of CamelCaseMessage
            $headers['resource'] = strtolower($matches[1][0]);
            $headers['endpoint'] = strtolower($matches[2][0]);
        }
        $headers = array_merge($this->defaultHeaders, $headers);
        foreach ($headers as &$value) $value = (string) $value;

        $stream = new Stream('php://memory', 'wb');
        $stream->write($body);

        $uri = (new Expressive\Uri());
        if (isset($headers['resource']) && isset($headers['endpoint'])) {
            $uri = $uri->withPath('/' . join('/', [
                $headers['resource'],
                isset($this->id) ? $this->id : '',
                $headers['endpoint']
            ]));
        }

        parent::__construct([], [], $uri, 'CONNECT', $stream, $headers, [], [], null, '1.0');
    }

	/**
	 * Return raw body of message. May not be even JSON.
	 */
	public function __toString() : string
	{
	    $vars = $this->toArray();
	    if ($vars) return $this->toJson();
	    else return $this->getBodyContents();
	}

	/**
	 * Save both headers and raw body. Useful in tests and logs.
	 */
    public function __serialize() : array
    {
        return [
            'headers' => $this->getHeaders(),
            'body' => (string) $this->getBody()
        ];
    }
    public function __unserialize(array $data)
    {
        $this->__construct($data['body']);
        $this->setHeaders($data['headers']);
    }

    static public function fromJson(string $json) : self
    {
        $json = json_decode($json, true);
        return new static($json);
    }

    public function getHydrator() : MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $this->hydrator = new MessageHydrator();
        }
        return $this->hydrator;
    }

    /**
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function & offsetGet ($header)
	{
	    if ($this->hasHeader($header)) {
    	    return $this->getHeader($header)[0];
	    } else {
	        $avoidReferenceError = null;
	        return $avoidReferenceError;
	    }
	}
	/**
	 * @param string $header
	 * @param string $value
	 */
	public function offsetSet ($header, $value) : void
	{
        unset($this[$header]);
        $this->headerNames[$header] = strtolower($header);
        $this->headers[$header]     = $this->filterHeaderValue($value);
	}
	public function offsetExists ($header) : bool
	{
	    return $this->hasHeader($header);
	}
	public function offsetUnset ($header) : void
	{
	    $normalized = strtolower($header);
        unset($this->headerNames[$normalized], $this->headers[$header]);
	}
    /**
     * @return array [
     *   *headers,
     *   *attributes,
     *   ...$properties
     * ]
     */
    public function getArrayCopy()
    {
        $array = $this->toArray();

        $array['*headers'] = [];
        foreach (array_keys($this->getHeaders()) as $name) {
            $array['*headers'][$name] = $this->getHeaderLine($name);
        }

        $array['*attributes'] = $this->getAttributes();

        return $array;
    }
    public function exchangeArray($input) : self
    {
        foreach ($input as $k => $v) $this->$k = $v;
        return $this;
    }

    /**
     * -----------
     * |  Body   |  -  Payloads in child classes.
     * -----------
     */
	public function toArray() : array
	{
	    // the reliable way to recursively get public props
        return json_decode(json_encode($this), true);
	}
	public function toJson() : string
	{
	    return json_encode_pretty($this->getHydrator()->extract($this));
	}

    /**
     * ----------------------
     * | ValidatorInterface |  -  Business model childs implement validation for itself.
     * ----------------------
     */
    public function isValid($value = null)
    {
        $value ??= $this;

        try {
            $value->getHeaders(); // to copy props to headers
        } catch (\Error $e) {
            if (strpos($e->getMessage(), 'accessed before initialization')) {
                $this->errorMessages['required_missing'] = $e->getMessage();
                return false;
            }
            throw $e;
        }
        return true;
    }

    public function getMessages()
    {
        return $this->errorMessages;
    }
}
