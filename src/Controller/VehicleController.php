<?php

namespace App\Controller;

// VehicleController.php — Gère l'affichage, le filtrage, la création, la modification et la suppression des véhicules.


use App\Entity\Feature;
use App\Entity\Reservation;
use App\Entity\Vehicle;
use App\Entity\TypeVehicle;
use App\Form\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;

final class VehicleController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('vehicle/index.html.twig');
    }

    #[Route('/vehicles', name: 'app_vehicle')]
    public function vehicles(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        $form = $this->createFormBuilder(null, [
            'method'          => 'GET',
            'csrf_protection' => false,
        ])
            ->add('startDate', DateType::class, [
                'required' => false,
                'widget'   => 'single_text',
                'label'    => 'Disponible à partir du ',
            ])
            ->add('endDate', DateType::class, [
                'required' => false,
                'widget'   => 'single_text',
                'label'    => 'Jusqu\'au ',
            ])
            ->add('type', EntityType::class, [
                'class'        => TypeVehicle::class,
                'choice_label' => 'name',
                'placeholder'  => 'vehicles.tous',
                'required'     => false,
                'label'        => 'vehicles.type',
            ])
            ->add('capacity', IntegerType::class, [
                'required' => false,
                'label'    => 'vehicles.capacity',
                'attr'     => ['min' => 1],
            ])
            ->add('priceMin', IntegerType::class, [
                'required' => false,
                'label'    => 'vehicles.priceMin',
                'attr'     => ['min' => 1],
            ])
            ->add('priceMax', IntegerType::class, [
                'required' => false,
                'label'    => 'vehicles.priceMax',
                'attr'     => ['min' => 1],
            ])
            ->add('features', EntityType::class, [
                'class'        => Feature::class,
                'choice_label' => 'name',
                'multiple'     => true,
                'expanded'     => true,
                'required'     => false,
                'label'        => 'vehicles.features',
            ])
            ->getForm();

        $form->handleRequest($request);

        $queryBuilder = $entityManager
            ->getRepository(Vehicle::class)
            ->createQueryBuilder('v');

        $data = $request->query->all('form');

        if ($data) {

            // TYPE
            if (!empty($data['type'])) {
                $type = $entityManager->getRepository(TypeVehicle::class)->find($data['type']);

                if (!$type) {
                    $pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 3);
                    return $this->render('vehicle/vehicles.html.twig', [
                        'form'       => $form->createView(),
                        'pagination' => $pagination,
                    ], new Response('', 422));
                }

                $queryBuilder->andWhere('v.typeVehicle = :type')
                             ->setParameter('type', $type);
            }

            // CAPACITY
            if (!empty($data['capacity'])) {
                $queryBuilder->andWhere('v.capacity >= :capacity')
                             ->setParameter('capacity', $data['capacity']);
            }

            // PRICE MIN
            if (!empty($data['priceMin'])) {
                $queryBuilder->andWhere('v.price >= :priceMin')
                             ->setParameter('priceMin', $data['priceMin']);
            }

            // PRICE MAX
            if (!empty($data['priceMax'])) {
                $queryBuilder->andWhere('v.price <= :priceMax')
                             ->setParameter('priceMax', $data['priceMax']);
            }

            // FEATURES
            if (!empty($data['features'])) {
                foreach ($data['features'] as $index => $feature) {
                    $queryBuilder->andWhere(':feature' . $index . ' MEMBER OF v.features')
                                 ->setParameter('feature' . $index, $feature);
                }
            }

            // DISPONIBILITÉ PAR DATES
            if (!empty($data['startDate']) && !empty($data['endDate'])) {
                $startDate = new \DateTime($data['startDate']);
                $endDate   = new \DateTime($data['endDate']);

                $reservedIds = $entityManager->getRepository(Reservation::class)
                    ->createQueryBuilder('r')
                    ->select('IDENTITY(r.vehicle)')
                    ->where('r.startDate < :endDate')
                    ->andWhere('r.endDate > :startDate')
                    ->setParameter('startDate', $startDate)
                    ->setParameter('endDate', $endDate)
                    ->getQuery()
                    ->getSingleColumnResult();

                if (!empty($reservedIds)) {
                    $queryBuilder->andWhere('v.id NOT IN (:reservedIds)')
                                 ->setParameter('reservedIds', $reservedIds);
                }
            }
        }

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            3
        );

        return $this->render('vehicle/vehicles.html.twig', [
            'pagination' => $pagination,
            'form'       => $form->createView(),
        ]);
    }

    #[Route('/reservation/{id}', name: 'reservation_vehicle')]
    public function reservation(Vehicle $vehicle, Request $request): Response
    {
    // Les dates viennent du formulaire de filtre sous form[startDate]
    $formData  = $request->query->all('form');
    $startDate = $formData['startDate'] ?? $request->query->get('startDate') ?? null;
    $endDate   = $formData['endDate']   ?? $request->query->get('endDate')   ?? null;

    return $this->redirectToRoute('app_reservation', [
        'id'        => $vehicle->getId(),
        'startDate' => $startDate,
        'endDate'   => $endDate,
        ]);
    }

    #[Route('/details/{id}', name: 'details_vehicle')]
    public function details(Vehicle $vehicle, EntityManagerInterface $entityManager): Response
    {
        $features = $entityManager->getRepository(Feature::class)->findAll();
        return $this->render('vehicle/details.html.twig', [
            'vehicle'  => $vehicle,
            'features' => $features,
        ]);
    }

    #[Route('/admin/vehicle/edit_or_create/{id?}', name: 'vehicle_edit_or_create')]
    public function editOrCreate(Request $request, EntityManagerInterface $entityManager, ?Vehicle $vehicle = null): Response
    {
        if (!$vehicle instanceof Vehicle) {
            $vehicle = new Vehicle();
        }

        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $vehicle->getId() === null;

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile instanceof UploadedFile) {
                $uploadsDir  = $this->getParameter('kernel.project_dir') . '/assets/img';
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadsDir, $newFilename);
                $vehicle->setImagePath('img/' . $newFilename);
            }

            if ($vehicle->getImagePath() == null) {
                $form->get('imageFile')->addError(new FormError("Veuillez télécharger une image valide (JPG ou PNG)."));
            }

            $typeVehicle = $vehicle->getTypeVehicle();

            if ($typeVehicle instanceof TypeVehicle) {
                $featureNames = [];
                foreach ($vehicle->getFeatures() as $feature) {
                    $featureNames[] = $feature->getName();
                }

                if ($typeVehicle->getName() === 'Utilitaire' && !in_array('Caméra de recul', $featureNames)) {
                    $form->addError(new FormError('Un véhicule de type Utilitaire doit avoir la caractéristique "Caméra de recul".'));
                }

                if ($typeVehicle->getName() === '4x4' && !in_array('GPS', $featureNames)) {
                    $form->addError(new FormError('Un véhicule de type 4x4 doit avoir la caractéristique "GPS".'));
                }
            }

            if ($form->isValid()) {
                $entityManager->persist($vehicle);
                $entityManager->flush();

                $this->addFlash(
                    $isNew ? 'success' : 'warning',
                    $isNew ? 'Le véhicule a bien été créé.' : 'Le véhicule a bien été mis à jour.'
                );

                return $this->redirectToRoute('app_vehicle');
            }
        }

        return $this->render('vehicle/edit_or_create.html.twig', [
            'form'    => $form,
            'vehicle' => $vehicle,
        ]);
    }

    #[Route('/vehicle/confirm-delete/{id}', name: 'vehicle_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(Request $request, EntityManagerInterface $entityManager, Vehicle $vehicle): Response
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('vehicle_confirm_delete', ['id' => $vehicle->getId()]))
            ->setMethod('POST')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($vehicle);
            $entityManager->flush();

            $this->addFlash('danger', 'Le véhicule a bien été supprimé.');

            return $this->redirectToRoute('app_vehicle');
        }

        return $this->render('vehicle/confirm_delete.html.twig', [
            'vehicle' => $vehicle,
            'form'    => $form->createView(),
        ]);
    }
}