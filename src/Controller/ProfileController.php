<?php

namespace App\Controller;

// ProfileController.php — Gère l'affichage et la modification du profil utilisateur (infos personnelles, adresse postale, mot de passe).

use App\Entity\Reservation;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use App\Form\ReservationFormType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    // -------------------------------------------------------
    // Affichage du profil
    // -------------------------------------------------------
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    // -------------------------------------------------------
    // Édition des données personnelles (prénom, nom, adresse postale)
    // -------------------------------------------------------
    #[Route('/profile/edit', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le formulaire met déjà à jour l'entité $user via data_class
            $em->flush();
            
            $this->addFlash('success', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
        ]);
    }

    // -------------------------------------------------------
    // Changement de mot de passe
    // -------------------------------------------------------
    #[Route('/profile/change-password', name: 'app_profile_change_password')]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer,
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();

            if (!$hasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            $newPassword = $form->get('newPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $newPassword));
            $em->flush();

            // 📧 Envoi du mail de confirmation à l'utilisateur
            if ($user->getEmail()) {
                try {
                    $emailUser = (new TemplatedEmail())
                        ->from(new Address('no-reply@magicvehicles.com', 'Magic Vehicles'))
                        ->to((string) $user->getEmail())
                        ->subject('🔐 Votre mot de passe a été modifié')
                        ->htmlTemplate('profile/password_changed_email.html.twig')
                        ->context(['user' => $user]);
                    
                    $mailer->send($emailUser);
                    $this->addFlash('success', 'Mot de passe modifié avec succès. Un email de confirmation vous a été envoyé.');
                } catch (\Exception $e) {
                    // En cas d'erreur d'envoi, on prévient l'utilisateur mais on continue
                    $this->addFlash('warning', 'Mot de passe modifié avec succès, mais l\'email de confirmation n\'a pas pu être envoyé.');
                }
            } else {
                $this->addFlash('success', 'Mot de passe modifié avec succès.');
            }
            
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    // -------------------------------------------------------
    // Affichage de toutes les réservations
    // -------------------------------------------------------
    #[Route('/profile/reservations', name: 'app_profile_reservations')]
    public function reservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        $now  = new \DateTime();

        $pastReservations   = $reservationRepository->findPastReservations($user, $now);
        $futureReservations = $reservationRepository->findFutureReservations($user, $now);

        return $this->render('profile/reservations.html.twig', [
            'pastReservations'   => $pastReservations,
            'futureReservations' => $futureReservations,
        ]);
    }

    // -------------------------------------------------------
    // Modification d'une réservation future
    // -------------------------------------------------------
    #[Route('/profile/reservations/{id}/edit', name: 'app_profile_reservation_edit')]
    public function editReservation(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepository,
    ): Response {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStartDate() <= new \DateTime()) {
            $this->addFlash('danger', 'Impossible de modifier une réservation passée.');
            return $this->redirectToRoute('app_profile_reservations');
        }

        $form = $this->createForm(ReservationFormType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startDate = $reservation->getStartDate();
            $endDate   = $reservation->getEndDate();

            if (!$reservationRepository->isVehicleAvailableExcept(
                $reservation->getVehicle()->getId(),
                $startDate,
                $endDate,
                $reservation->getId()
            )) {
                $this->addFlash('danger', 'Ce véhicule n\'est pas disponible sur cette période.');
                return $this->redirectToRoute('app_profile_reservation_edit', ['id' => $reservation->getId()]);
            }

            $days = $startDate->diff($endDate)->days;
            $reservation->setTotalPrice($days * $reservation->getVehicle()->getPrice());

            $em->flush();

            $this->addFlash('success', 'Réservation modifiée avec succès.');
            return $this->redirectToRoute('app_profile_reservations');
        }

        return $this->render('profile/reservation_edit.html.twig', [
            'form'        => $form,
            'reservation' => $reservation,
        ]);
    }

    // -------------------------------------------------------
    // Suppression d'une réservation future
    // -------------------------------------------------------
    #[Route('/profile/reservations/{id}/delete', name: 'app_profile_reservation_delete', methods: ['POST'])]
    public function deleteReservation(
        Reservation $reservation,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($reservation->getStartDate() <= new \DateTime()) {
            $this->addFlash('danger', 'Impossible de supprimer une réservation passée.');
            return $this->redirectToRoute('app_profile_reservations');
        }

        if ($this->isCsrfTokenValid('delete_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée avec succès.');
        }

        return $this->redirectToRoute('app_profile_reservations');
    }
}