<?php

namespace dvictorjhg\braidphp\Core\Attributes;

use Attribute;
use PHPInjector\Container\Container;

/**
 * Describes a module's imports, providers, controllers, and bootstrap entries.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Module
{
    /** @var Container<mixed>|null */
    private(set) ?Container $bootstrap = null;
    /** @var Container<mixed>|null */
    private(set) ?Container $controllers = null;
    /** @var Container<mixed>|null */
    private(set) ?Container $imports = null;
    /** @var Container<mixed>|null */
    private(set) ?Container $providers = null;

    /**
     * @param Container<mixed>|array<mixed>|null $bootstrap Entries resolved after
     *     providers and controllers have been registered.
     * @param Container<mixed>|array<mixed>|null $controllers Classes scanned for
     *     route attributes.
     * @param Container<mixed>|array<mixed>|null $imports Modules bootstrapped
     *     before this module.
     * @param Container<mixed>|array<mixed>|null $providers Values or classes
     *     registered with the injector.
     */
    public function __construct(
        Container|array|null $bootstrap = null,
        Container|array|null $controllers = null,
        Container|array|null $imports = null,
        Container|array|null $providers = null
    ) {
        $this->bootstrap = self::toContainer($bootstrap);
        $this->controllers = self::toContainer($controllers);
        $this->imports = self::toContainer($imports);
        $this->providers = self::toContainer($providers);
    }

    /**
     * @param Container<mixed>|array<mixed>|null $values
     * @return Container<mixed>|null
     */
    private static function toContainer(Container|array|null $values): ?Container
    {
        if ($values === null || $values === []) {
            return null;
        }

        return $values instanceof Container ? $values : new Container($values);
    }
}
