<?php declare(strict_types=1);

namespace Saloon\Http\Faking;

use ReflectionClass;
use Saloon\Http\Request;
use Saloon\Http\Connector;
use Saloon\Helpers\URLHelper;
use Saloon\Contracts\Response;
use Saloon\Http\PendingRequest;
use Saloon\Helpers\ReflectionHelper;
use PHPUnit\Framework\Assert as PHPUnit;
use Saloon\Contracts\MockClient as MockClientContract;
use Saloon\Exceptions\InvalidConnectorException;
use Saloon\Exceptions\NoMockResponseFoundException;
use Saloon\Exceptions\InvalidMockResponseCaptureMethodException;

class MockClient implements MockClientContract
{
    /**
     * Collection of all the responses that will be sequenced.
     *
     * @var array
     */
    protected array $sequenceResponses = [];

    /**
     * Collection of responses used only when a connector is called.
     *
     * @var array
     */
    protected array $connectorResponses = [];

    /**
     * Collection of responses used only when a request is called.
     *
     * @var array
     */
    protected array $requestResponses = [];

    /**
     * Collection of responses that will run when the request is matched.
     *
     * @var array
     */
    protected array $urlResponses = [];

    /**
     * Collection of all the recorded responses.
     *
     * @var array
     */
    protected array $recordedResponses = [];

    /**
     * Constructor
     *
     * @param array $mockData
     * @throws InvalidMockResponseCaptureMethodException
     */
    public function __construct(array $mockData = [])
    {
        $this->addResponses($mockData);
    }

    /**
     * Store the mock responses in the correct places.
     *
     * @param array $responses
     * @return void
     * @throws InvalidMockResponseCaptureMethodException
     */
    public function addResponses(array $responses): void
    {
        foreach ($responses as $key => $response) {
            if (is_int($key)) {
                $key = null;
            }

            $this->addResponse($response, $key);
        }
    }

    /**
     * Add a mock response to the client
     *
     * @param MockResponse|Fixture|callable $response
     * @param string|null $captureMethod
     * @return void
     * @throws InvalidMockResponseCaptureMethodException
     */
    public function addResponse(MockResponse|Fixture|callable $response, ?string $captureMethod = null): void
    {
        if (is_null($captureMethod)) {
            $this->sequenceResponses[] = $response;

            return;
        }

        if (! is_string($captureMethod)) {
            throw new InvalidMockResponseCaptureMethodException;
        }

        // Let's detect if the capture method is either a connector or
        // a request. If so we'll put them in their designated arrays.

        if ($captureMethod && class_exists($captureMethod)) {
            $reflection = new ReflectionClass($captureMethod);

            if ($reflection->isSubclassOf(Connector::class)) {
                $this->connectorResponses[$captureMethod] = $response;

                return;
            }

            if ($reflection->isSubclassOf(Request::class)) {
                $this->requestResponses[$captureMethod] = $response;

                return;
            }
        }

        // Otherwise, the keys must be a URL.

        $this->urlResponses[$captureMethod] = $response;
    }

    /**
     * Get the next response in the sequence
     *
     * @return mixed
     */
    public function getNextFromSequence(): mixed
    {
        return array_shift($this->sequenceResponses);
    }

    /**
     * Guess the next response based on the request.
     *
     * @param PendingRequest $pendingRequest
     * @return MockResponse|Fixture
     * @throws NoMockResponseFoundException
     * @throws \Sammyjo20\Saloon\Exceptions\SaloonInvalidConnectorException
     */
    public function guessNextResponse(PendingRequest $pendingRequest): MockResponse|Fixture
    {
        $request = $pendingRequest->getRequest();
        $requestClass = get_class($request);

        if (array_key_exists($requestClass, $this->requestResponses)) {
            return $this->mockResponseValue($this->requestResponses[$requestClass], $pendingRequest);
        }

        $connectorClass = get_class($request->connector());

        if (array_key_exists($connectorClass, $this->connectorResponses)) {
            return $this->mockResponseValue($this->connectorResponses[$connectorClass], $pendingRequest);
        }

        $guessedResponse = $this->guessResponseFromUrl($request);

        if (! is_null($guessedResponse)) {
            return $this->mockResponseValue($guessedResponse, $pendingRequest);
        }

        if (empty($this->sequenceResponses)) {
            throw new NoMockResponseFoundException;
        }

        return $this->mockResponseValue($this->getNextFromSequence(), $pendingRequest);
    }

    /**
     * Guess the response from the URL.
     *
     * @param Request $request
     * @return MockResponse|Fixture|callable|null
     * @throws InvalidConnectorException
     */
    private function guessResponseFromUrl(Request $request): MockResponse|Fixture|callable|null
    {
        foreach ($this->urlResponses as $url => $response) {
            if (! URLHelper::matches($url, $request->getRequestUrl())) {
                continue;
            }

            return $response;
        }

        return null;
    }

    /**
     * Check if the responses are empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->sequenceResponses) && empty($this->connectorResponses) && empty($this->requestResponses) && empty($this->urlResponses);
    }

    /**
     * Record a response.
     *
     * @param Response $response
     * @return void
     */
    public function recordResponse(Response $response): void
    {
        $this->recordedResponses[] = $response;
    }

    /**
     * Get all the recorded responses
     *
     * @return array
     */
    public function getRecordedResponses(): array
    {
        return $this->recordedResponses;
    }

    /**
     * Get the last request that the mock manager sent.
     *
     * @return Request|null
     */
    public function getLastRequest(): ?Request
    {
        return $this->getLastResponse()?->getRequest();
    }

    /**
     * Get the last response that the mock manager sent.
     *
     * @return Response|null
     */
    public function getLastResponse(): ?Response
    {
        if (empty($this->recordedResponses)) {
            return null;
        }

        $lastResponse = end($this->recordedResponses);

        reset($this->recordedResponses);

        return $lastResponse;
    }

    /**
     * Assert that a given request was sent.
     *
     * @param string|callable $value
     * @return void
     * @throws \ReflectionException|\Sammyjo20\Saloon\Exceptions\SaloonInvalidConnectorException
     */
    public function assertSent(string|callable $value): void
    {
        $result = $this->checkRequestWasSent($value);

        PHPUnit::assertTrue($result, 'An expected request was not sent.');
    }

    /**
     * Assert that a given request was not sent.
     *
     * @param string|callable $request
     * @return void
     * @throws \ReflectionException|\Sammyjo20\Saloon\Exceptions\SaloonInvalidConnectorException
     */
    public function assertNotSent(string|callable $request): void
    {
        $result = $this->checkRequestWasNotSent($request);

        PHPUnit::assertTrue($result, 'An unexpected request was sent.');
    }

    /**
     * Assert JSON data was sent
     *
     * @param string $request
     * @param array $data
     * @return void
     * @throws \ReflectionException|\Sammyjo20\Saloon\Exceptions\SaloonInvalidConnectorException
     */
    public function assertSentJson(string $request, array $data): void
    {
        $this->assertSent(function ($currentRequest, $currentResponse) use ($request, $data) {
            return $currentRequest instanceof $request && $currentResponse->json() === $data;
        });
    }

    /**
     * Assert that nothing was sent.
     *
     * @return void
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->getRecordedResponses(), 'Requests were sent.');
    }

    /**
     * Assert a request count has been met.
     *
     * @param int $count
     * @return void
     */
    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->getRecordedResponses());
    }

    /**
     * Check if a given request was sent
     *
     * @param string|callable $request
     * @return bool
     * @throws \ReflectionException
     * @throws \Sammyjo20\Saloon\Exceptions\SaloonInvalidConnectorException
     */
    protected function checkRequestWasSent(string|callable $request): bool
    {
        $passed = false;

        if (is_callable($request)) {
            return $this->checkClosureAgainstResponses($request);
        }

        if (is_string($request)) {
            if (class_exists($request) && ReflectionHelper::isSubclassOf($request, Request::class)) {
                $passed = $this->findResponseByRequest($request) instanceof Response;
            } else {
                $passed = $this->findResponseByRequestUrl($request) instanceof Response;
            }
        }

        return $passed;
    }

    /**
     * Check if a request has not been sent.
     *
     * @param string|callable $request
     * @return bool
     * @throws \ReflectionException
     * @throws \Sammyjo20\Saloon\Exceptions\SaloonInvalidConnectorException
     */
    protected function checkRequestWasNotSent(string|callable $request): bool
    {
        return ! $this->checkRequestWasSent($request);
    }

    /**
     * Assert a given request was sent.
     *
     * @param string $request
     * @return Response|null
     */
    public function findResponseByRequest(string $request): ?Response
    {
        if ($this->checkHistoryEmpty() === true) {
            return null;
        }

        $lastRequest = $this->getLastRequest();

        if ($lastRequest instanceof $request) {
            return $this->getLastResponse();
        }

        foreach ($this->getRecordedResponses() as $recordedResponse) {
            if ($recordedResponse->getOriginalRequest() instanceof $request) {
                return $recordedResponse;
            }
        }

        return null;
    }

    /**
     * Find a request that matches a given url pattern
     *
     * @param string $url
     * @return Response|null
     * @throws \Sammyjo20\Saloon\Exceptions\SaloonInvalidConnectorException
     */
    public function findResponseByRequestUrl(string $url): ?Response
    {
        if ($this->checkHistoryEmpty() === true) {
            return null;
        }

        $lastRequest = $this->getLastRequest();

        if ($lastRequest instanceof Request && URLHelper::matches($url, $lastRequest->getFullRequestUrl())) {
            return $this->getLastResponse();
        }

        foreach ($this->getRecordedResponses() as $response) {
            $request = $response->getOriginalRequest();

            if (URLHelper::matches($url, $request->getFullRequestUrl())) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Test if the closure can pass with the history.
     *
     * @param callable $closure
     * @return bool
     */
    private function checkClosureAgainstResponses(callable $closure): bool
    {
        if ($this->checkHistoryEmpty() === true) {
            return false;
        }

        // Let's first check if the latest response resolves the callable
        // with a successful result.

        $lastResponse = $this->getLastResponse();

        if ($lastResponse instanceof Response) {
            $passed = $closure($lastResponse->getOriginalRequest(), $lastResponse);

            if ($passed === true) {
                return true;
            }
        }

        // If it was not the previous response, we should iterate through each of the
        // responses and break out if we get a match.

        foreach ($this->getRecordedResponses() as $response) {
            $request = $response->getOriginalRequest();

            $passed = $closure($request, $response);

            if ($passed === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Will return true if the history is empty.
     *
     * @return bool
     */
    private function checkHistoryEmpty(): bool
    {
        return count($this->recordedResponses) <= 0;
    }

    /**
     * Get the mock value.
     *
     * @param MockResponse|Fixture|callable $mockable
     * @param PendingRequest $pendingRequest
     * @return MockResponse|Fixture
     */
    private function mockResponseValue(MockResponse|Fixture|callable $mockable, PendingRequest $pendingRequest): MockResponse|Fixture
    {
        if ($mockable instanceof MockResponse) {
            return $mockable;
        }

        if ($mockable instanceof Fixture) {
            return $mockable;
        }

        return $mockable($pendingRequest);
    }
}
