<?php
namespace Neos\Flow\Mvc;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\ContentStream;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;

/**
 * The minimal MVC response object.
 * It allows for simple interactions with the HTTP response from within MVC actions. More specific requirements can be implemented via HTTP Components.
 * @see setComponentParameter()
 *
 * @Flow\Proxy(false)
 * @api
 *
 * TODO: getters + merge methods instead of renderer.
 */
final class ActionResponse
{
    /**
     * @var Stream
     */
    protected $content;

    /**
     * @var array
     */
    protected $componentParameters = [];

    /**
     * @var UriInterface
     */
    protected $redirectUri;

    /**
     * The HTTP status code
     *
     * @var integer
     */
    protected $statusCode = 200;

    /**
     * @var string
     */
    protected $contentType;

    /**
     * @param string|StreamInterface $content
     * @return void
     * @api
     */
    public function setContent($content): void
    {
        if (is_string($content)) {
            $content = new Stream(fopen('data://text/plain,' . $content, 'rb'));
        }
        $this->content = $content;
    }

    /**
     * Set content mime type for this response.
     *
     * @param string $contentType
     * @return void
     * @api
     */
    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    /**
     * Set a redirect URI and according status for this response.
     *
     * @param UriInterface $uri
     * @param int $statusCode
     * @return void
     * @api
     */
    public function setRedirectUri(UriInterface $uri, int $statusCode = 303): void
    {
        $this->redirectUri = $uri;
        $this->statusCode = $statusCode;
    }

    /**
     * Set the status code for this response as HTTP status code.
     * Other codes than HTTP status may end in unpredictable results.
     *
     * @param int $statusCode
     * @return void
     * @api
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * Set a (HTTP) component parameter for use later in the chain.
     * This can be used to adjust all aspects of the later processing if needed.
     *
     * @param string $componentClassName
     * @param string $parameterName
     * @param mixed $value
     * @return void
     * @api
     */
    public function setComponentParameter(string $componentClassName, string $parameterName, $value): void
    {
        if (!isset($this->componentParameters[$componentClassName])) {
            $this->componentParameters[$componentClassName] = [];
        }
        $this->componentParameters[$componentClassName][$parameterName] = $value;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        $content = $this->content->getContents();
        $this->content->rewind();
        return $content;
    }

    /**
     * @return array
     */
    public function getComponentParameters(): array
    {
        return $this->componentParameters;
    }

    /**
     * @return UriInterface
     */
    public function getRedirectUri():? UriInterface
    {
        return $this->redirectUri;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @param ActionResponse $actionResponse
     * @return ActionResponse
     */
    public function mergeIntoParentResponse(ActionResponse $actionResponse): ActionResponse
    {
        if (!empty($this->content)) {
            $actionResponse->setContent($this->getContent());
        }
        if ($this->contentType !== null) {
            $actionResponse->setContentType($this->contentType);
        }

        if ($this->statusCode !== null) {
            $actionResponse->setStatusCode($this->statusCode);
        }

        if ($this->redirectUri !== null) {
            $actionResponse->setRedirectUri($this->redirectUri);
        }

        foreach ($this->componentParameters as $componentClass => $parameters) {
            foreach ($parameters as $parameterName => $parameterValue) {
                $actionResponse->setComponentParameter($componentClass, $parameterName, $parameterValue);
            }
        }

        return $actionResponse;
    }

    /**
     * @param ComponentContext $componentContext
     * @return ComponentContext
     */
    public function mergeIntoComponentContext(ComponentContext $componentContext): ComponentContext
    {
        $httpResponse = $componentContext->getHttpResponse();
        $httpResponse = $httpResponse
            ->withStatus($this->statusCode);

        if ($this->content !== null) {
            $httpResponse = $httpResponse->withBody(ContentStream::fromContents($this->getContent()));
        }

        if ($this->contentType) {
            $httpResponse = $httpResponse->withHeader('Content-Type', $this->contentType);
        }

        if ($this->redirectUri) {
            $httpResponse = $httpResponse->withHeader('Location', (string)$this->redirectUri);
        }

        foreach ($this->componentParameters as $componentClassName => $componentParameterGroup) {
            foreach ($componentParameterGroup as $parameterName => $parameterValue) {
                $componentContext->setParameter($componentClassName, $parameterName, $parameterValue);
            }
        }

        $componentContext->replaceHttpResponse($httpResponse);

        return $componentContext;
    }
}
