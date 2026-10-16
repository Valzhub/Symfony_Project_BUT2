<?php

namespace App\Controller;

// LocaleController.php — Gère le changement de langue de l'application en stockant la locale choisie en session.


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'change_locale')]
    public function changeLocale(string $locale, Request $request): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer');
        return new RedirectResponse($referer ?: $this->generateUrl('home'));
    }
}