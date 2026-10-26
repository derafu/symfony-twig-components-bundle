<?php

declare(strict_types=1);

namespace Derafu\TestsTwigComponentsBundle;

use Composer\InstalledVersions;
use Derafu\TestsTwigComponentsBundle\Fixture\Kernel;
use Derafu\TwigComponents\Abstract\AbstractComponent;
use Derafu\TwigComponents\Component\Block\AlertComponent;
use Derafu\TwigComponents\Exception\TwigComponentException;
use Derafu\TwigComponentsBundle\DependencyInjection\Compiler\TemplatePathsPass;
use Derafu\TwigComponentsBundle\TwigComponentsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\ComponentFactory;
use Twig\Environment;

#[CoversClass(TwigComponentsBundle::class)]
#[CoversClass(TemplatePathsPass::class)]
final class ServicesTest extends TestCase
{
    private string $project;

    private Kernel $kernel;

    /** @var array<array-key, mixed> */
    private array $environment;

    /** @var array<array-key, mixed> */
    private array $server;

    private string|false $verbosity;

    protected function setUp(): void
    {
        $this->environment = $_ENV;
        $this->server = $_SERVER;
        $this->verbosity = getenv('SHELL_VERBOSITY');
        $this->project = sys_get_temp_dir() . '/derafu-twig-components-bundle-' . bin2hex(random_bytes(8));
        (new Filesystem())->mirror(__DIR__ . '/../fixtures/site', $this->project);
        $this->kernel = new Kernel($this->project);
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->project);
        $_ENV = $this->environment;
        $_SERVER = $this->server;
        putenv($this->verbosity === false ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY=' . $this->verbosity);
    }

    private function twig(): Environment
    {
        $this->kernel->boot();
        $twig = $this->kernel->getContainer()->get('test.twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }

    private function factory(): ComponentFactory
    {
        $this->kernel->boot();
        $factory = $this->kernel->getContainer()->get('test.ux.twig_component.component_factory');
        self::assertInstanceOf(ComponentFactory::class, $factory);

        return $factory;
    }

    public function testBundlePathAndConfigurationAlias(): void
    {
        $bundle = new TwigComponentsBundle();
        self::assertSame(dirname(__DIR__, 2), $bundle->getPath());
        self::assertSame('derafu_twig_components', $bundle->getContainerExtension()?->getAlias());
    }

    public function testPackageYamlCompilesAndItsComponentsRemainPrivate(): void
    {
        $factory = $this->factory();
        $container = $this->kernel->getContainer();
        self::assertFalse($container->has(AlertComponent::class));
        self::assertSame('components/block-alert.html.twig', $factory->metadataFor('block-alert')->getTemplate());
        self::assertInstanceOf(AlertComponent::class, $factory->create('block-alert', ['content' => 'A component'])->getComponent());
        self::assertSame(InstalledVersions::getInstallPath('derafu/twig-components'), $container->getParameter('derafu_twig_components.package_dir'));
    }

    public function testEveryShippedComponentIsAvailableThroughTheNativeFactory(): void
    {
        $factory = $this->factory();
        $templates = glob(InstalledVersions::getInstallPath('derafu/twig-components') . '/resources/templates/components/*.html.twig');
        self::assertIsArray($templates);
        self::assertCount(37, $templates);
        foreach ($templates as $template) {
            $name = basename($template, '.html.twig');
            $metadata = $factory->metadataFor($name);
            self::assertTrue(is_subclass_of($metadata->getClass(), AbstractComponent::class), $name);
            self::assertInstanceOf($metadata->getClass(), $factory->get($name));
            self::assertTrue($this->twig()->getLoader()->exists($metadata->getTemplate()));
        }
    }

    public function testRenderingUsesStrictSymfonyTwigAndKeepsApplicationComponents(): void
    {
        $twig = $this->twig();
        self::assertTrue($twig->isStrictVariables());
        self::assertSame($twig, $this->kernel->getContainer()->get('test.environment'));
        $html = $twig->render('page.html.twig');
        self::assertStringContainsString('A native Derafu component', $html);
        self::assertStringContainsString('alert-success', $html);
        self::assertStringContainsString('Application extension', $html);
        self::assertStringContainsString('<aside>An application component</aside>', $html);
        self::assertFalse(InstalledVersions::isInstalled('derafu/content'));
        self::assertFalse(InstalledVersions::isInstalled('derafu/twig'));
    }

    public function testComponentsHaveIndependentInstancesAndGeneratedIds(): void
    {
        $factory = $this->factory();
        $first = $factory->create('block-alert', ['content' => 'First'])->getComponent();
        $second = $factory->create('block-alert', ['content' => 'Second'])->getComponent();
        self::assertInstanceOf(AlertComponent::class, $first);
        self::assertInstanceOf(AlertComponent::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame('First', $first->getContent());
        self::assertSame('Second', $second->getContent());
        self::assertNotSame($first->getId(), $second->getId());
    }

    public function testPackageTranslationsAreConsumedBySymfonyTranslator(): void
    {
        $twig = $this->twig();
        $translator = $this->kernel->getContainer()->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertSame('Cerrar', $translator->trans('Close', [], 'twig', 'es'));
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale('es');
        $html = $twig->createTemplate('<twig:block-alert content="Translated" dismissible />')->render();
        self::assertStringContainsString('aria-label="Cerrar"', $html);
    }

    public function testNestedComponentExceptionMessagesAreTranslated(): void
    {
        $factory = $this->factory();
        $translator = $this->kernel->getContainer()->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        try {
            $factory->create('block-accordion', ['items' => [['content' => 'Missing title']]]);
            self::fail('Invalid component data must fail.');
        } catch (TwigComponentException $exception) {
            self::assertSame('Componente block-accordion: El título del ítem es obligatorio.', $exception->trans($translator, 'es'));
        }
    }

    public function testApplicationTemplatesOverrideThePackageFallbacks(): void
    {
        file_put_contents($this->project . '/templates/components/block-alert.html.twig', '<section class="custom-alert">{{ this.content }}</section>');
        $html = $this->twig()->createTemplate('<twig:block-alert content="Application override" />')->render();
        self::assertSame('<section class="custom-alert">Application override</section>', $html);
    }

    public function testApplicationTranslationsOverrideThePackageCatalogues(): void
    {
        mkdir($this->project . '/translations');
        file_put_contents($this->project . '/translations/twig+intl-icu.es.yaml', "Close: 'Application close'\n");
        $this->kernel->boot();
        $translator = $this->kernel->getContainer()->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertSame('Application close', $translator->trans('Close', [], 'twig', 'es'));
    }

    public function testTheBundleDoesNotDependOnBundleRegistrationOrder(): void
    {
        $this->kernel = new Kernel($this->project, true);
        self::assertStringContainsString('A native Derafu component', $this->twig()->render('page.html.twig'));
    }

    public function testARealSymfonyRequestRendersTheComponents(): void
    {
        $response = $this->kernel->handle(Request::create('/components'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('A native Derafu component', $response->getContent() ?: '');
        self::assertStringContainsString('An application component', $response->getContent() ?: '');
    }
}
