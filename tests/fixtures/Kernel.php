<?php

declare(strict_types=1);

namespace Derafu\TestsTwigComponentsBundle\Fixture;

use Derafu\TranslationBundle\TranslationBundle;
use Derafu\TwigComponentsBundle\TwigComponentsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Environment;

/** A real application with no Content, Derafu renderer or Derafu Twig service. */
final class Kernel extends BaseKernel
{
    public function __construct(private readonly string $project, private readonly bool $bundleFirst = false)
    {
        parent::__construct('test', true);
    }

    /** @return iterable<BundleInterface> */
    public function registerBundles(): iterable
    {
        if ($this->bundleFirst) {
            yield new TwigComponentsBundle();
        }
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new TranslationBundle();
        if (!$this->bundleFirst) {
            yield new TwigComponentsBundle();
        }
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'twig-components-test',
                'default_locale' => 'en',
                'translator' => ['fallbacks' => ['en']],
                'router' => ['resource' => $this->project . '/routes.yaml'],
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => false],
            ]);
            $container->loadFromExtension('twig', [
                'default_path' => $this->project . '/templates',
                'strict_variables' => true,
            ]);
            $container->loadFromExtension('derafu_twig_components');
            $container->register(AppComponent::class)->setAutoconfigured(true)->setAutowired(true);
            $container->register(AppExtension::class)->setAutoconfigured(true);
            $container->register(Controller::class)->setAutowired(true)->setPublic(true);
            $container->register('logger', \Psr\Log\NullLogger::class);
            foreach (['twig', 'translator', 'ux.twig_component.component_factory'] as $id) {
                $container->setAlias('test.' . $id, $id)->setPublic(true);
            }
            $container->setAlias('test.environment', Environment::class)->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return $this->project;
    }

    public function getCacheDir(): string
    {
        return $this->project . '/var/cache';
    }

    public function getLogDir(): string
    {
        return $this->project . '/var/log';
    }
}
