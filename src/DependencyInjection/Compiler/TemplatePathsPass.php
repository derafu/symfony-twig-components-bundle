<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Twig Components Bundle - Native Symfony integration for Derafu UI components.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TwigComponentsBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Appends package templates after the application's Twig paths. */
final class TemplatePathsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('twig.loader.native_filesystem')->addMethodCall('addPath', [
            '%derafu_twig_components.templates_dir%',
        ]);
    }
}
