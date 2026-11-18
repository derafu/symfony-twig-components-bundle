<?php

declare(strict_types=1);

namespace Derafu\TestsTwigComponentsBundle\Fixture;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class Controller
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function index(): Response
    {
        return new Response($this->twig->render('page.html.twig'));
    }
}
