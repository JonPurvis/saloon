<?php declare(strict_types=1);

namespace Saloon\Traits\Request;

use Saloon\Http\Connector;
use Saloon\Exceptions\InvalidConnectorException;

trait HasConnector
{
    /**
     * The loaded connector used in requests.
     *
     * @var Connector|null
     */
    private ?Connector $loadedConnector = null;

    /**
     * Retrieve the loaded connector.
     *
     * @return Connector
     * @throws InvalidConnectorException
     */
    public function connector(): Connector
    {
        return $this->loadedConnector ??= $this->resolveConnector();
    }

    /**
     * Set the loaded connector at runtime.
     *
     * @param Connector $connector
     * @return $this
     */
    public function setConnector(Connector $connector): static
    {
        $this->loadedConnector = $connector;

        return $this;
    }

    /**
     * Create a new connector instance.
     *
     * @return Connector
     * @throws InvalidConnectorException
     */
    protected function resolveConnector(): Connector
    {
        if (empty($this->connector) || ! class_exists($this->connector)) {
            throw new InvalidConnectorException;
        }

        return new $this->connector;
    }
}
