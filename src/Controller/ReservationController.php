<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    #[Route(path: '/reserveren', name: 'reservation_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('reservation/call.html.twig');
    }
}
