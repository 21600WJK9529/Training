<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Service\ContactNotificationService;
use App\Service\ContactService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contacts', name: 'contact_')]
final class ContactController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContactService $contactService,
        ContactNotificationService $contactNotificationService
    ): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $contactService->create($contact);
                $contactNotificationService->logContactCreatedNotifications($contact);
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError('This email address is already in use.'));

                return $this->render('contact/index.html.twig', [
                    'contactForm' => $form->createView(),
                    'contacts' => $contactService->listAll(),
                ]);
            }

            $this->addFlash('success', 'Contact created successfully.');

            return $this->redirectToRoute('contact_index');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
            'contacts' => $contactService->listAll(),
        ]);
    }
}
