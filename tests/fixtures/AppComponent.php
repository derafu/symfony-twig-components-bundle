<?php

declare(strict_types=1);

namespace Derafu\TestsTwigComponentsBundle\Fixture;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('app-note')]
final class AppComponent
{
    public string $text = '';
}
