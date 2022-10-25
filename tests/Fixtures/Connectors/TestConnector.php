<?php

namespace Sammyjo20\Saloon\Tests\Fixtures\Connectors;

use GuzzleHttp\Promise\PromiseInterface;
use Sammyjo20\Saloon\Clients\MockClient;
use Sammyjo20\Saloon\Http\SaloonConnector;
use Sammyjo20\Saloon\Http\SaloonRequest;
use Sammyjo20\Saloon\Interfaces\SaloonResponseInterface;
use Sammyjo20\Saloon\Traits\Plugins\AcceptsJson;
use Sammyjo20\Saloon\Interfaces\Data\SendsJsonBody;

class TestConnector extends SaloonConnector implements SendsJsonBody
{
    use AcceptsJson;

    /**
     * Define the base url of the api.
     *
     * @return string
     */
    public function defineBaseUrl(): string
    {
        return apiUrl();
    }

    /**
     * Define the base headers that will be applied in every request.
     *
     * @return string[]
     */
    protected function defaultHeaders(): array
    {
        return [];
    }

    protected function defaultData(): mixed
    {
        return [
            'baz' => 'bash',
        ];
    }
}
