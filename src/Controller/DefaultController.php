<?php

declare(strict_types=1);

namespace App\Controller;

use OpenDxp\Bundle\AdminBundle\Controller\Admin\LoginController;
use OpenDxp\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends FrontendController
{
    public function defaultAction(): Response
    {
        return $this->render('default/default.html.twig');
    }

    /**
     * Forwards the request to admin login
     */
    #[Route('/login', name: 'app_login', methods: 'GET')]
    public function loginAction(): Response
    {
        return $this->forward(LoginController::class.'::loginCheckAction');
    }
}
