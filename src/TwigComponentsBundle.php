<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Twig Components Bundle - Native Symfony integration for Derafu UI components.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TwigComponentsBundle;

use Composer\InstalledVersions;
use Derafu\TwigComponentsBundle\DependencyInjection\Compiler\TemplatePathsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/** Registers the package's existing components in Symfony UX. */
final class TwigComponentsBundle extends AbstractBundle
{
    protected string $extensionAlias = 'derafu_twig_components';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TemplatePathsPass());
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->parameters()->set('derafu_twig_components.package_dir', InstalledVersions::getInstallPath('derafu/twig-components'));
        $configurator->import(InstalledVersions::getInstallPath('derafu/twig-components') . '/resources/config/twig-components-services.yaml');
        $configurator->import('../resources/config/symfony-twig-components-bundle-services.yaml');
    }
}
