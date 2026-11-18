<?php

declare(strict_types=1);

namespace Derafu\TestsTwigComponentsBundle\Fixture;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('app_label', static fn (): string => 'Application extension')];
    }
}
