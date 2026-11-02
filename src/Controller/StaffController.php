<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationFormType;
use App\Repository\ReservationRepository;
use App\Service\ReservationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/dashboard')]
class StaffController extends AbstractController
{
    #[Route(name: 'reservation_dashboard')]
    public function index(ReservationRepository $reservations): Response
    {
        return $this->render('reservation/dashboard.html.twig', [
            'reservations' => $reservations->findUpcoming(),
        ]);
    }

    #[Route(path: '/reserveren', name: 'reservation_create', methods: ['GET', 'POST'])]
    public function create(ReservationManager $manager, Request $request): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationFormType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reasons = $manager->findBlockingReasons($reservation);
            if ([] !== $reasons) {
                return $this->render('reservation/new.html.twig', [
                    'form' => $form,
                    'errors' => $reasons,
                ]);
            }

            try {
                $manager->create($reservation);
            } catch (\DomainException $exception) {
                return $this->render('reservation/new.html.twig', [
                    'form' => $form,
                    'errors' => [$exception->getMessage()],
                ]);
            }

            $this->addFlash('success', new TranslatableMessage('flash.reserved'));

            return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/new.html.twig', [
            'form' => $form,
            'errors' => $form->isSubmitted() ? $this->collectFormErrors($form) : [],
        ]);
    }

    #[Route(path: '/reserveren/{id}', name: 'reservation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ReservationRepository $reservations): Response
    {
        $reservation = $reservations->find($id);
        if (null === $reservation) {
            throw $this->createNotFoundException('Deze reservering bestaat niet.');
        }

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route(path: '/reserveren/{id}/status', name: 'reservation_set_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function setStatus(
        int $id,
        Request $request,
        ReservationRepository $reservations,
        ReservationManager $manager,
        TranslatorInterface $translator,
    ): Response {
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid('reservation_status_'.$id, $token)) {
            throw $this->createAccessDeniedException($translator->trans('Invalid CSRF token.', [], 'security'));
        }

        $reservation = $reservations->find($id);
        if (null === $reservation) {
            throw $this->createNotFoundException('Deze reservering bestaat niet.');
        }

        $status = $this->normalizeStatus($request->request->get('status'));
        try {
            $manager->setStatus($reservation, $status);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('reservation_dashboard');
        }

        $this->addFlash('success', new TranslatableMessage('flash.status_changed', [
            '%id%' => $id,
            '%status%' => $translator->trans('status.'.$status),
        ]));

        return $this->redirectToRoute('reservation_dashboard');
    }

    private function normalizeStatus(mixed $raw): string
    {
        if (!is_string($raw)) {
            throw new BadRequestHttpException('Reserveringsstatus ontbreekt of is ongeldig.');
        }

        $allowed = [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_CANCELLED,
        ];

        if (!in_array($raw, $allowed, true)) {
            throw new BadRequestHttpException('Onbekende reserveringsstatus.');
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private function collectFormErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(deep: true, flatten: true) as $error) {
            if ($error instanceof FormError) {
                $errors[] = $error->getMessage();
            }
        }

        return $errors;
    }
}
