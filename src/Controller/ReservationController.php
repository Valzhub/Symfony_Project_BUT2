<?php

namespace App\Controller;

// ReservationController.php — Gère la création d'une réservation et la génération du bon de réservation.

use App\Entity\Reservation;
use App\Entity\Vehicle;
use App\Form\ReservationFormType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    // Formulaire de réservation d'un véhicule
    #[Route('/reservation/new/{id}', name: 'app_reservation')]
    public function new(
        Vehicle $vehicle,
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepository,
        MailerInterface $mailer,
    ): Response {
        $reservation = new Reservation();
        $reservation->setVehicle($vehicle);

        if ($request->query->get('startDate')) {
            $reservation->setStartDate(new \DateTime($request->query->get('startDate')));
        }
        if ($request->query->get('endDate')) {
            $reservation->setEndDate(new \DateTime($request->query->get('endDate')));
        }

        $form = $this->createForm(ReservationFormType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startDate = $reservation->getStartDate();
            $endDate   = $reservation->getEndDate();

            if (!$reservationRepository->isVehicleAvailable($vehicle->getId(), $startDate, $endDate)) {
                $this->addFlash('danger', 'Ce véhicule n\'est pas disponible sur cette période.');
                return $this->redirectToRoute('app_reservation', ['id' => $vehicle->getId()]);
            }

            $reservation->setUser($this->getUser());
            $reservation->setIsConfirmed(true);
            $reservation->setConfirmationToken(Uuid::v4()->toRfc4122());

            $days = $startDate->diff($endDate)->days;
            $reservation->setTotalPrice($days * $vehicle->getPrice());

            $em->persist($reservation);
            $em->flush();

            $emailUser = (new TemplatedEmail())
                ->from(new Address('contact@magicvehicles.com', 'Magic Vehicles'))
                ->to((string) $this->getUser()->getEmail())
                ->subject('Confirmation de votre réservation')
                ->htmlTemplate('reservation/email.html.twig')
                ->context(['reservation' => $reservation, 'vehicle' => $vehicle]);

            $mailer->send($emailUser);

            $this->addFlash('success', 'Réservation confirmée ! Un email de confirmation vous a été envoyé.');
            return $this->redirectToRoute('app_reservation_bon', ['token' => $reservation->getConfirmationToken()]);
        }

        return $this->render('reservation/form.html.twig', [
            'vehicle' => $vehicle,
            'form'    => $form,
        ]);
    }

  
    // Affichage du bon de réservation
    #[Route('/reservation/bon/{token}', name: 'app_reservation_bon')]
    public function bon(string $token, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->findOneBy(['confirmationToken' => $token]);

        if (!$reservation) {
            throw $this->createNotFoundException('Bon de réservation introuvable.');
        }

        return $this->render('reservation/bon.html.twig', [
            'reservation' => $reservation,
            'vehicle'     => $reservation->getVehicle(),
        ]);
    }

    // Génération du bon de réservation en PDF
    #[Route('/reservation/bon/{token}/pdf', name: 'app_reservation_bon_pdf')]
    public function bonPdf(string $token, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->findOneBy(['confirmationToken' => $token]);

        if (!$reservation) {
            throw $this->createNotFoundException('Bon de réservation introuvable.');
        }

        $html = $this->renderView('reservation/bon_pdf.html.twig', [
            'reservation' => $reservation,
            'vehicle'     => $reservation->getVehicle(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="bon-reservation.pdf"',
            ]
        );
    }
}