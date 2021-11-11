<?php

// Transforms a RequestInterface to a ServerRequestInterface
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class TestTransformer implements ServerRequestInterface
{
    public function __construct(
        private RequestInterface $request,
        private array $serverParams = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private array $uploadedFiles = [],
        private null|array|object $parsedBody = null,
        private array $attributes = [],
    )
    {
    }

    public function getProtocolVersion(): string
    {
        return $this->request->getProtocolVersion();
    }

    public function withProtocolVersion($version): self
    {
        return new self($this->request->withProtocolVersion($version));
    }

    public function getHeaders()
    {
        return $this->request->getHeaders();
    }

    public function hasHeader($name)
    {
        return $this->request->hasHeader($name);
    }

    public function getHeader($name)
    {
        return $this->request->getHeader($name);
    }

    public function getHeaderLine($name)
    {
        return $this->request->getHeaderLine($name);
    }

    public function withHeader($name, $value)
    {
        return new self($this->request->withHeader($name, $value));
    }

    public function withAddedHeader($name, $value)
    {
        return new self($this->request->withAddedHeader($name, $value));
    }

    public function withoutHeader($name)
    {
        return new self($this->request->withoutHeader($name));
    }

    public function getBody()
    {
        return $this->request->getBody();
    }

    public function withBody(StreamInterface $body)
    {
        return $this->request->withBody($body);
    }

    public function getRequestTarget()
    {
        return $this->request->getRequestTarget();
    }

    public function withRequestTarget($requestTarget)
    {
        return new self($this->request->withRequestTarget($requestTarget));
    }

    public function getMethod()
    {
        return $this->request->getMethod();
    }

    public function withMethod($method)
    {
        return new self($this->request->withMethod($method));
    }

    public function getUri()
    {
        return $this->request->getUri();
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        return new self($this->request->withUri($uri, $preserveHost));
    }

    public function getServerParams()
    {
        return $this->serverParams;
    }

    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies)
    {
        return new self(
            $this->request,
            $this->serverParams,
            $cookies,
            $this->queryParams,
            $this->uploadedFiles,
            $this->parsedBody,
            $this->attributes
        );
    }

    public function getQueryParams()
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query)
    {
        return new self(
            $this->request,
            $this->serverParams,
            $this->cookieParams,
            $query,
            $this->uploadedFiles,
            $this->parsedBody,
            $this->attributes
        );
    }

    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        return new self(
            $this->request,
            $this->serverParams,
            $this->cookieParams,
            $this->queryParams,
            $uploadedFiles,
            $this->parsedBody,
            $this->attributes
        );
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data)
    {
        return new self(
            $this->request,
            $this->serverParams,
            $this->cookieParams,
            $this->queryParams,
            $this->uploadedFiles,
            $data,
            $this->attributes
        );
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    private function withAttributes($attributes): self
    {
        return new self(
            $this->request,
            $this->serverParams,
            $this->cookieParams,
            $this->queryParams,
            $this->uploadedFiles,
            $this->parsedBody,
            $attributes,
        );
    }

    public function withAttribute($name, $value)
    {
        return $this->withAttributes(array_merge($this->attributes, [$name => $value]));
    }

    public function withoutAttribute($name)
    {
        $attributes = $this->attributes;
        if(array_key_exists($name, $attributes)) {
            unset($attributes[$name]);
        }
        return $this->withAttributes($attributes);
    }
}