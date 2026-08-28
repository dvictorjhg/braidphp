<?php

namespace dvictorjhg\braidphp\Core\Scanners;

use ReflectionClass;
use dvictorjhg\braidphp\Core\Attributes\Module;

class ModuleScanner
{
    /**
     * Scans a module for a Module attribute and returns a Module object or null if no Module attribute is found.
     *
     * @param object|class-string $module The module to scan for a Module attribute.
     */
    public static function scan(string|object $module): ?Module
    {
        $reflectionClass = new ReflectionClass($module);
        $moduleAttributes = $reflectionClass->getAttributes(Module::class);

        if (!empty($moduleAttributes)) {
            return $moduleAttributes[0]->newInstance();
        }

        return null;
    }
}
