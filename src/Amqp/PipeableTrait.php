<?php
namespace Amqp;

use Laminas\Diactoros\RequestTrait;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Can be processed by Zend Expressive middleware pipeline.
 * It is possible to use both as request and response.
 */
trait PipeableTrait
{
    use RequestTrait;

    /**
     * Delived class uses RequestTrait which copies private props to it.
     * It makes inherited methods to use child's private prop which in null.
     * To be consistent reload methods to force parent to use its own private props.
     */
    public function getBody() : StreamInterface { return parent::getBody(); }
    public function getUri() : UriInterface { return parent::getUri(); }
    public function withUri(UriInterface $uri, $preserveHost = false) : RequestInterface {
        return parent::withUri($uri, $preserveHost);
    }

    public function getBodyContents() : string
    {
        return (string) $this->getBody();
    }

    public function getHeaders() : array
    {
        foreach ($this->PROPS_TO_HEADERS as $key) {
            if (isset($this->$key) && !$this->hasHeader($key)) {
                $val = $this->$key;
                if (is_bool($val)) $this[$key] = $val ? 'true' : 'false';
                else $this[$key] = (string) $val;
            }
        }
        return parent::getHeaders();
    }

    public function getReasonPhrase() : string
    {
        return $this->reasonPhrase;
    }

    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    /**
     * Assign multiple attributes in one call.
     *
     * @param array $attrs
     * @return \Laminas\Diactoros\ServerRequest
     */
    public function withAttributes(array $attrs = []) : ServerRequest
    {
        $new = $this;
        foreach ($attrs as $name => $value) {
            $new = $new->withAttribute($name, $value);
        }
        return $new;
    }

    public function withBodyContents(string $body) : self
    {
        $new = clone $this;
        $new->getBody()->write($body);
        return $new;
    }

    public function withHeaders(array $headers = []) : ServerRequest
    {
        $new = $this;
        foreach ($headers as $name => $value) {
            $new = $new->withHeader($name, $value);
        }
        return $new;
    }

    public function withStatus($code, $reasonPhrase = '') : ServerRequest
    {
        $new = clone $this;
        $this->statusCode = $code;
        $this->reasonPhrase = $reasonPhrase;
        return $new;
    }
}
