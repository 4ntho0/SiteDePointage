<?php

// src/Controller/LegalController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CguController extends AbstractController
{
    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('pages/cgu.html.twig');
    }
}
